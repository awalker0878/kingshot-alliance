<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\ActivePlayerEventVisibilityResolver;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Participation\Models\EventResponse;
use App\Contexts\Operations\Participation\Services\EventRegistrationWindow;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Contexts\Operations\Polls\Models\EventPollVote;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final readonly class EventAttentionQuery
{
    public function __construct(
        private ActivePlayerEventVisibilityResolver $visibility,
        private EventWorkflowGuard $workflows,
        private EventRegistrationWindow $window,
    ) {}

    /** @return list<array<string, mixed>> */
    public function for(PlayerReference $actor, int $days = 14): array
    {
        $player = $actor;
        $eligibleTargets = $this->visibility->targetIds($actor);
        if ($eligibleTargets['player'] === [] && $eligibleTargets['alliance'] === [] && $eligibleTargets['kingdom'] === []) {
            return [];
        }

        $occurrences = EventOccurrence::query()
            ->where('status', EventOccurrenceStatus::Scheduled->value)
            ->whereBetween('starts_at', [now(), now()->addDays(max(1, min(90, $days)))])
            ->whereHas('event', function (Builder $query) use ($eligibleTargets): void {
                $query->where(function (Builder $scopeQuery) use ($eligibleTargets): void {
                    $this->applyTargetScope($scopeQuery, $eligibleTargets);
                });
            })
            ->with(['event.eventType.workflowDimensions'])
            ->orderBy('starts_at')
            ->limit(100)
            ->get();

        if ($occurrences->isEmpty()) {
            return [];
        }

        $occurrenceIds = array_values($occurrences->pluck('id')->map(static fn ($id): string => (string) $id)->all());
        $facts = $this->participationFacts($occurrenceIds, $player);

        $items = [];
        foreach ($occurrences as $occurrence) {
            $event = $occurrence->event;
            $occurrenceId = (string) $occurrence->id;
            $participationEnabled = $this->workflows->supports($event, EventWorkflowDimension::Participation);

            if ($participationEnabled
                && ! isset($facts['responses'][$occurrenceId])) {
                $items[] = $this->item($occurrence, $player, 'response');
            }

            if ($participationEnabled
                && $this->window->for($event, $occurrence)['is_open']
                && ! isset($facts['registrations'][$occurrenceId])) {
                $items[] = $this->item($occurrence, $player, 'registration');
            }

            foreach ($facts['polls'][$occurrenceId] ?? [] as $pollId) {
                if (! isset($facts['votes'][$pollId])) {
                    $items[] = $this->item($occurrence, $player, 'vote', $pollId);
                }
            }

            if ($this->workflows->supports($event, EventWorkflowDimension::Roster)
                && isset($facts['roster_assignments'][$occurrenceId])) {
                $items[] = $this->item(
                    $occurrence,
                    $player,
                    'roster_confirmation',
                    rosterMemberId: $facts['roster_assignments'][$occurrenceId],
                );
            }
        }

        return $items;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array{alliance:list<string>,player:list<string>,kingdom:list<string>}  $targets
     */
    private function applyTargetScope(Builder $query, array $targets): void
    {
        $hasTargets = false;

        if ($targets['player'] !== []) {
            $query->where(function (Builder $q) use ($targets): void {
                $q->where('scope', EventScope::Player->value)->whereIn('player_id', $targets['player']);
            });
            $hasTargets = true;
        }

        if ($targets['alliance'] !== []) {
            $method = $hasTargets ? 'orWhere' : 'where';
            $query->{$method}(function (Builder $q) use ($targets): void {
                $q->where('scope', EventScope::Alliance->value)->whereIn('alliance_id', $targets['alliance']);
            });
            $hasTargets = true;
        }

        if ($targets['kingdom'] !== []) {
            $method = $hasTargets ? 'orWhere' : 'where';
            $query->{$method}(function (Builder $q) use ($targets): void {
                $q->where('scope', EventScope::Kingdom->value)->whereIn('kingdom_id', $targets['kingdom']);
            });
            $hasTargets = true;
        }

        if (! $hasTargets) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * @param  list<string>  $occurrenceIds
     * @return array{
     *   responses:array<string,bool>,
     *   registrations:array<string,bool>,
     *   polls:array<string,list<string>>,
     *   votes:array<string,bool>,
     *   roster_assignments:array<string,string>
     * }
     */
    private function participationFacts(array $occurrenceIds, PlayerReference $player): array
    {
        $responses = EventResponse::query()
            ->where('player_id', $player->playerId)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->pluck('occurrence_id')
            ->mapWithKeys(static fn ($id): array => [(string) $id => true])
            ->all();

        $registrations = EventRegistration::query()
            ->where('player_id', $player->playerId)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->whereIn('status', [EventRegistrationStatus::Registered->value, EventRegistrationStatus::Waitlisted->value])
            ->pluck('occurrence_id')
            ->mapWithKeys(static fn ($id): array => [(string) $id => true])
            ->all();

        $pollModels = EventPoll::query()
            ->whereIn('occurrence_id', $occurrenceIds)
            ->where('status', EventPollStatus::Open->value)
            ->where(static fn (Builder $query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(static fn (Builder $query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->get(['id', 'occurrence_id']);

        /** @var array<string,list<string>> $polls */
        $polls = $pollModels
            ->groupBy(static fn (EventPoll $poll): string => (string) $poll->occurrence_id)
            ->map(static fn (Collection $group): array => $group
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->values()
                ->all())
            ->all();

        $pollIds = array_values($pollModels->pluck('id')->map(static fn ($id): string => (string) $id)->all());
        $votes = $pollIds === []
            ? []
            : EventPollVote::query()
                ->where('player_id', $player->playerId)
                ->whereIn('poll_id', $pollIds)
                ->pluck('poll_id')
                ->mapWithKeys(static fn ($id): array => [(string) $id => true])
                ->all();

        /** @var array<string,string> $rosterAssignments */
        $rosterAssignments = [];
        $members = EventRosterMember::query()
            ->where('player_id', $player->playerId)
            ->where('status', EventRosterMemberStatus::Assigned->value)
            ->whereHas('roster', static fn (Builder $query) => $query->whereIn('occurrence_id', $occurrenceIds))
            ->with('roster:id,occurrence_id')
            ->orderBy('assigned_at')
            ->get(['id', 'roster_id', 'assigned_at']);

        foreach ($members as $member) {
            $roster = $member->roster;
            if (! $roster instanceof EventRoster) {
                continue;
            }

            $occurrenceId = (string) $roster->occurrence_id;
            $rosterAssignments[$occurrenceId] ??= (string) $member->id;
        }

        return [
            'responses' => $responses,
            'registrations' => $registrations,
            'polls' => $polls,
            'votes' => $votes,
            'roster_assignments' => $rosterAssignments,
        ];
    }

    /** @return array<string, mixed> */
    private function item(EventOccurrence $occurrence, PlayerReference $player, string $action, ?string $pollId = null, ?string $rosterMemberId = null): array
    {
        return [
            'occurrenceId' => (string) $occurrence->id,
            'eventId' => (string) $occurrence->event_id,
            'eventTypeSlug' => (string) $occurrence->event->eventType->slug,
            'nameKey' => (string) $occurrence->event->eventType->name_key,
            'title' => $occurrence->event->title,
            'action' => $action,
            'pollId' => $pollId,
            'rosterMemberId' => $rosterMemberId,
            'playerId' => (string) $player->playerId,
            'startsAt' => $occurrence->starts_at->toIso8601String(),
        ];
    }
}
