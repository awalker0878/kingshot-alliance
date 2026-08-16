<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Queries;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Models\EventTypeScope;
use App\Contexts\Operations\EventCore\Services\ActivePlayerEventVisibilityResolver;
use App\Contexts\Operations\EventCore\Services\EventCapabilityResolver;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Participation\Models\EventResponse;
use App\Contexts\Operations\Participation\Services\EventRegistrationWindow;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Contexts\Operations\Polls\Models\EventPollVote;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final readonly class EventAttentionQuery
{
    public function __construct(
        private ActivePlayerEventVisibilityResolver $visibility,
        private EventCapabilityResolver $capabilities,
        private EventRegistrationWindow $window,
    ) {}

    /** @return list<array<string, mixed>> */
    public function for(Player $actor, int $days = 14): array
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
            ->with(['event.eventType', 'event.typeScope.capabilities'])
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
            $configuration = $event->typeScope;
            if (! $configuration instanceof EventTypeScope) {
                continue;
            }
            $occurrenceId = (string) $occurrence->id;

            if ($this->capabilities->supports($configuration, EventCapability::Responses)
                && ! isset($facts['responses'][$occurrenceId])) {
                $items[] = $this->item($occurrence, $player, 'response');
            }

            if ($this->capabilities->supports($configuration, EventCapability::Registration)
                && $this->window->for($event, $occurrence)['is_open']
                && ! isset($facts['registrations'][$occurrenceId])) {
                $items[] = $this->item($occurrence, $player, 'registration');
            }

            if ($this->capabilities->supports($configuration, EventCapability::Polls)) {
                foreach ($facts['polls'][$occurrenceId] ?? [] as $pollId) {
                    if (! isset($facts['votes'][$pollId])) {
                        $items[] = $this->item($occurrence, $player, 'vote', $pollId);
                    }
                }
            }

            if ($this->capabilities->supports($configuration, EventCapability::Rosters)
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
     *   responses:array<string,true>,
     *   registrations:array<string,true>,
     *   polls:array<string,list<string>>,
     *   votes:array<string,true>,
     *   roster_assignments:array<string,string>
     * }
     */
    private function participationFacts(array $occurrenceIds, Player $player): array
    {
        $responses = EventResponse::query()
            ->where('player_id', $player->id)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->pluck('occurrence_id')
            ->mapWithKeys(static fn ($id): array => [(string) $id => true])
            ->all();

        $registrations = EventRegistration::query()
            ->where('player_id', $player->id)
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
                ->where('player_id', $player->id)
                ->whereIn('poll_id', $pollIds)
                ->pluck('poll_id')
                ->mapWithKeys(static fn ($id): array => [(string) $id => true])
                ->all();

        $rosterAssignments = EventRosterMember::query()
            ->where('player_id', $player->id)
            ->where('status', EventRosterMemberStatus::Assigned->value)
            ->whereHas('roster', static fn (Builder $query) => $query->whereIn('occurrence_id', $occurrenceIds))
            ->with('roster:id,occurrence_id')
            ->orderBy('assigned_at')
            ->get(['id', 'roster_id', 'assigned_at'])
            ->reduce(static function (array $carry, EventRosterMember $member): array {
                $occurrenceId = (string) $member->roster->occurrence_id;
                $carry[$occurrenceId] ??= (string) $member->id;

                return $carry;
            }, []);

        return [
            'responses' => $responses,
            'registrations' => $registrations,
            'polls' => $polls,
            'votes' => $votes,
            'roster_assignments' => $rosterAssignments,
        ];
    }

    /** @return array<string, mixed> */
    private function item(EventOccurrence $occurrence, Player $player, string $action, ?string $pollId = null, ?string $rosterMemberId = null): array
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
            'playerId' => (string) $player->id,
            'startsAt' => $occurrence->starts_at->toIso8601String(),
        ];
    }
}
