<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventVisibilityResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;

final readonly class EventCalendarQuery
{
    public function __construct(
        private EventVisibilityResolver $visibility,
        private EventAuthorization $authorization,
    ) {}

    /** @return Collection<int, EventOccurrence> */
    public function forAlliance(PlayerReference $actor, string $allianceId, int $pastDays = 0, int $futureDays = 30): Collection
    {
        $pastDays = max(0, min($pastDays, 31));
        $futureDays = max(1, min($futureDays, 366));

        $this->authorization->authorize(
            $actor->playerId,
            EventScope::Alliance,
            $allianceId,
            OperationsPermission::EventAllianceView,
        );

        return EventOccurrence::query()
            ->where('status', EventOccurrenceStatus::Scheduled->value)
            ->whereBetween('starts_at', [now()->subDays($pastDays), now()->addDays($futureDays)])
            ->whereHas('event', static fn (Builder $query) => $query
                ->where('scope', EventScope::Alliance->value)
                ->where('alliance_id', $allianceId))
            ->with(['event.eventType'])
            ->orderBy('starts_at')
            ->limit(100)
            ->get();
    }

    /** @return Collection<int, EventOccurrence> */
    public function calendar(PlayerReference $actor, int $pastDays = 7, int $futureDays = 90): Collection
    {
        $pastDays = max(0, min($pastDays, 31));
        $futureDays = max(1, min($futureDays, 366));
        $targets = $this->visibility->targetIds($actor);

        return EventOccurrence::query()
            ->where('status', EventOccurrenceStatus::Scheduled->value)
            ->whereBetween('starts_at', [now()->subDays($pastDays), now()->addDays($futureDays)])
            ->whereHas('event', function (Builder $query) use ($targets): void {
                $query->where(function (Builder $scopeQuery) use ($targets): void {
                    $hasTargets = false;
                    foreach ([
                        EventScope::Alliance->value => ['ids' => $targets['alliance'], 'column' => 'alliance_id'],
                        EventScope::Player->value => ['ids' => $targets['player'], 'column' => 'player_id'],
                        EventScope::Kingdom->value => ['ids' => $targets['kingdom'], 'column' => 'kingdom_id'],
                    ] as $scope => $selection) {
                        if ($selection['ids'] === []) {
                            continue;
                        }
                        $method = $hasTargets ? 'orWhere' : 'where';
                        $scopeQuery->{$method}(static fn (Builder $q) => $q
                            ->where('scope', $scope)
                            ->whereIn($selection['column'], $selection['ids']));
                        $hasTargets = true;
                    }
                    if (! $hasTargets) {
                        $scopeQuery->whereRaw('1 = 0');
                    }
                });
            })
            ->with(['event.eventType.workflowDimensions', 'event.typeScope'])
            ->orderBy('starts_at')
            ->limit(500)
            ->get();
    }

    public function occurrence(PlayerReference $actor, string $occurrenceId): EventOccurrence
    {
        $occurrence = EventOccurrence::query()
            ->whereKey($occurrenceId)
            ->with(['event.eventType.workflowDimensions', 'event.typeScope'])
            ->firstOrFail();
        $event = $occurrence->event;
        if (! $event instanceof Event) {
            throw (new ModelNotFoundException)->setModel(Event::class);
        }

        $this->authorize($actor, $event, (string) $event->typeScope->view_permission_key);

        return $occurrence;
    }

    public function eventForManage(PlayerReference $actor, string $eventId): Event
    {
        $event = Event::query()
            ->whereKey($eventId)
            ->with(['eventType.workflowDimensions', 'typeScope', 'occurrences'])
            ->firstOrFail();
        $this->authorize($actor, $event, (string) $event->typeScope->manage_permission_key);

        return $event;
    }

    private function authorize(PlayerReference $actor, Event $event, string $permissionKey): void
    {
        $scope = $event->scopeEnum();
        $this->authorization->authorize(
            $actor->playerId,
            $scope,
            $this->targetId($event, $scope),
            OperationsPermission::from($permissionKey),
        );
    }

    private function targetId(Event $event, EventScope $scope): string
    {
        $targetId = match ($scope) {
            EventScope::Alliance => $event->alliance_id,
            EventScope::Kingdom => $event->kingdom_id,
            EventScope::Player => $event->player_id,
        };

        if (! is_string($targetId) || $targetId === '') {
            throw new LogicException('Event target identity is missing.');
        }

        return $targetId;
    }
}
