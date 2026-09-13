<?php

declare(strict_types=1);

namespace App\ReadModels\EventManagement\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventStatus;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Queries\EventOccurrenceCatalogueQuery;
use App\Contexts\Operations\Events\Services\EventTypeProfileResolver;
use App\ReadModels\EventManagement\Enums\EventCommandItemStatus;
use App\ReadModels\EventManagement\Enums\EventCommandSeverity;
use App\ReadModels\EventManagement\Enums\EventCommandState;
use App\ReadModels\EventManagement\Support\EventCommandItems as Items;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class EventCommandQuery
{
    private const READINESS_HORIZON_DAYS = 7;

    private const CLOSEOUT_SELECTION_LIMIT = 12;

    public function __construct(
        private EventCommandOperationalReadinessQuery $operationalReadiness,
        private EventCommandContextReadinessQuery $contextReadiness,
        private EventCommandCloseoutQuery $closeout,
        private EventTypeProfileResolver $profiles,
        private EventOccurrenceCatalogueQuery $catalogue,
    ) {}

    /** @return array<string, mixed> */
    public function forEvent(PlayerReference $actor, Event $event, ?string $requestedOccurrenceId = null, ?string $cursor = null): array
    {
        $startedAt = hrtime(true);
        $page = $this->catalogue->forEvent($actor, (string) $event->id, $cursor);
        $event = $page['event'];
        $pagination = array_diff_key($page, ['event' => true, 'items' => true]);
        $profile = $this->profiles->resolve($event->eventType);
        $dimensions = $profile['profile_enabled'] === true ? $profile['workflow_dimensions'] : [];
        $commandDimensions = $this->has($dimensions, EventWorkflowDimension::ReadinessCloseout) ? $dimensions : [];
        $now = CarbonImmutable::now('UTC');
        $requested = trim((string) $requestedOccurrenceId);
        $occurrence = $requested === '' ? null : $page['items']->firstWhere('id', $requested);
        $occurrence ??= $this->selectOccurrence($actor, $event, $commandDimensions, $requestedOccurrenceId, $now);
        $occurrence?->setRelation('event', $event);

        if (! $occurrence instanceof EventOccurrence) {
            return $this->record($event, null, [
                'eventId' => (string) $event->id,
                'eventProfile' => $profile,
                'selectedOccurrenceId' => null,
                'occurrences' => [],
                'occurrencePage' => $pagination,
                'state' => null,
                'eventStatus' => $event->status->value,
                'occurrenceStatus' => null,
                'startsAt' => null,
                'endsAt' => null,
                'timezone' => (string) $event->timezone,
                'blockerCount' => 0,
                'warningCount' => 0,
                'sections' => [],
            ], $startedAt);
        }

        $cancelled = $this->cancelled($event, $occurrence);
        $ended = $this->ended($occurrence, $now);
        $active = ! $cancelled && $this->active($occurrence, $now);

        /** @var list<array<string, mixed>> $sections */
        $sections = match (true) {
            $cancelled => [$this->cancelledSection($event, $occurrence)],
            $ended => $this->closeout->forOccurrence($actor, $event, $occurrence, $commandDimensions),
            default => array_values(array_merge(
                $this->operationalReadiness->forOccurrence($event, $occurrence, $commandDimensions, $now),
                $this->contextReadiness->forOccurrence($actor, $event, $occurrence, $commandDimensions),
            )),
        };
        $items = Items::flatten($sections);
        $blockers = Items::blockers($items);
        $warnings = Items::warnings($items);
        $state = $cancelled ? null : $this->state($occurrence, $active, $ended, $blockers, $now);

        $choices = $page['items'];
        if (! $choices->contains('id', $occurrence->id)) {
            $choices->push($occurrence);
        }
        $occurrences = $choices
            ->sortBy('starts_at')
            ->values()
            ->map(static fn (EventOccurrence $item): array => [
                'id' => (string) $item->id,
                'startsAt' => $item->starts_at->toIso8601String(),
                'endsAt' => $item->ends_at->toIso8601String(),
                'status' => $item->status->value,
                'selected' => (string) $item->id === (string) $occurrence->id,
            ])
            ->all();

        return $this->record($event, $occurrence, [
            'eventId' => (string) $event->id,
            'eventProfile' => $profile,
            'selectedOccurrenceId' => (string) $occurrence->id,
            'occurrences' => array_values($occurrences),
            'occurrencePage' => $pagination,
            'state' => $state?->value,
            'eventStatus' => $event->status->value,
            'occurrenceStatus' => $occurrence->status->value,
            'startsAt' => $occurrence->starts_at->toIso8601String(),
            'endsAt' => $occurrence->ends_at->toIso8601String(),
            'timezone' => (string) $event->timezone,
            'blockerCount' => $blockers,
            'warningCount' => $warnings,
            'sections' => $sections,
        ], $startedAt);
    }

    /** @param list<string> $dimensions */
    private function selectOccurrence(
        PlayerReference $actor,
        Event $event,
        array $dimensions,
        ?string $requestedOccurrenceId,
        CarbonImmutable $now,
    ): ?EventOccurrence {
        $requestedOccurrenceId = trim((string) $requestedOccurrenceId);
        if ($requestedOccurrenceId !== '') {
            $requested = EventOccurrence::query()->where('event_id', $event->id)->whereKey($requestedOccurrenceId)->first();
            if (! $requested instanceof EventOccurrence) {
                throw ValidationException::withMessages(['occurrence' => 'The selected Event occurrence is not available for this Event.']);
            }

            return $requested;
        }

        $eligible = EventOccurrence::query()->where('event_id', $event->id)
            ->where('status', '!=', EventOccurrenceStatus::Cancelled->value);
        if ($event->status !== EventStatus::Cancelled) {
            $active = (clone $eligible)->where('status', '!=', EventOccurrenceStatus::Completed->value)
                ->where('starts_at', '<=', $now)->where('ends_at', '>', $now)
                ->orderBy('starts_at')->orderBy('id')->first();
            if ($active instanceof EventOccurrence) {
                return $active;
            }

            $ended = (clone $eligible)->where(static fn (Builder $query) => $query
                ->where('status', EventOccurrenceStatus::Completed->value)->orWhere('ends_at', '<=', $now))
                ->orderByDesc('ends_at')->orderByDesc('id')->limit(self::CLOSEOUT_SELECTION_LIMIT)->get();
            foreach ($ended as $item) {
                $item->setRelation('event', $event);
                $sections = $this->closeout->forOccurrence($actor, $event, $item, $dimensions);
                if (Items::blockers(Items::flatten($sections)) > 0) {
                    return $item;
                }
            }

            $upcoming = (clone $eligible)->where('starts_at', '>', $now)
                ->orderBy('starts_at')->orderBy('id')->first();
            if ($upcoming instanceof EventOccurrence) {
                return $upcoming;
            }
        }

        return EventOccurrence::query()->where('event_id', $event->id)
            ->orderByDesc('starts_at')->orderByDesc('id')->first();
    }

    /** @return array<string, mixed> */
    private function cancelledSection(Event $event, EventOccurrence $occurrence): array
    {
        return Items::section('schedule', 'events.command.sections.schedule', 'readiness', [
            Items::make('schedule.cancelled', 'readiness', EventCommandItemStatus::NotApplicable, EventCommandSeverity::Informational, 'operations.events', 'events.command.items.cancelled', handoff: Items::handoff($event, $occurrence, 'schedule', 'events.command.actions.reviewSchedule')),
        ]);
    }

    private function state(EventOccurrence $occurrence, bool $active, bool $ended, int $blockers, CarbonImmutable $now): EventCommandState
    {
        if ($active) {
            return EventCommandState::Active;
        }
        if ($ended) {
            return $blockers > 0 ? EventCommandState::CloseoutRequired : EventCommandState::Complete;
        }
        if ($blockers > 0) {
            return EventCommandState::NeedsAttention;
        }

        return $occurrence->starts_at->lessThanOrEqualTo($now->addDays(self::READINESS_HORIZON_DAYS))
            ? EventCommandState::Ready
            : EventCommandState::Planning;
    }

    private function cancelled(Event $event, EventOccurrence $occurrence): bool
    {
        return $event->status === EventStatus::Cancelled || $occurrence->status === EventOccurrenceStatus::Cancelled;
    }

    private function active(EventOccurrence $occurrence, CarbonImmutable $now): bool
    {
        return $occurrence->status !== EventOccurrenceStatus::Completed
            && $occurrence->status !== EventOccurrenceStatus::Cancelled
            && $occurrence->starts_at->lessThanOrEqualTo($now)
            && $occurrence->ends_at->greaterThan($now);
    }

    private function ended(EventOccurrence $occurrence, CarbonImmutable $now): bool
    {
        return $occurrence->status === EventOccurrenceStatus::Completed || $occurrence->ends_at->lessThanOrEqualTo($now);
    }

    /** @param list<string> $dimensions */
    private function has(array $dimensions, EventWorkflowDimension $dimension): bool
    {
        return in_array($dimension->value, $dimensions, true);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function record(Event $event, ?EventOccurrence $occurrence, array $payload, int $startedAt): array
    {
        Log::debug('event_command.rendered', [
            'event_id' => (string) $event->id,
            'occurrence_id' => $occurrence instanceof EventOccurrence ? (string) $occurrence->id : null,
            'state' => $payload['state'] ?? null,
            'blocker_count' => (int) ($payload['blockerCount'] ?? 0),
            'warning_count' => (int) ($payload['warningCount'] ?? 0),
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
        ]);

        return $payload;
    }
}
