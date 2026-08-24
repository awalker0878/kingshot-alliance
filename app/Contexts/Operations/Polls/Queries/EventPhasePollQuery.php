<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Polls\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventPhase;
use App\Contexts\Operations\Events\Services\EventPhaseService;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Contexts\Operations\Polls\Models\EventPollVote;
use Carbon\CarbonImmutable;

final readonly class EventPhasePollQuery
{
    public function __construct(private EventPhaseService $phases) {}

    /** @return array{phases:list<array<string,mixed>>,polls:list<array<string,mixed>>} */
    public function forOccurrence(EventOccurrence $occurrence, ?PlayerReference $player = null, bool $manager = false): array
    {
        $occurrence->loadMissing('event');
        $timezone = (string) $occurrence->event->timezone;
        $phaseRows = array_values(EventPhase::query()
            ->where('occurrence_id', $occurrence->id)
            ->orderBy('sort_order')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (EventPhase $phase): array => [
                'id' => (string) $phase->id,
                'key' => (string) $phase->key,
                'nameKey' => $phase->name_key,
                'name' => $phase->name,
                'type' => $phase->phase_type->value,
                'startsAt' => $phase->starts_at?->toIso8601String(),
                'endsAt' => $phase->ends_at?->toIso8601String(),
                'startsLocal' => $phase->starts_at?->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'endsLocal' => $phase->ends_at?->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'status' => $this->phases->effectiveStatus($phase)->value,
                'storedStatus' => $phase->status->value,
                'sortOrder' => (int) $phase->sort_order,
            ])->all());

        $pollQuery = EventPoll::query()
            ->where('occurrence_id', $occurrence->id)
            ->with(['options', 'votes']);
        if (! $manager) {
            $pollQuery->whereIn('status', [EventPollStatus::Open->value, EventPollStatus::Closed->value]);
        }

        $pollRows = array_values($pollQuery->orderBy('created_at')->get()->map(function (EventPoll $poll) use ($player, $manager, $timezone): array {
            $selected = $player instanceof PlayerReference
                ? EventPollVote::query()->where('poll_id', $poll->id)->where('player_id', $player->playerId)->pluck('option_id')->map(static fn ($id): string => (string) $id)->all()
                : [];
            $counts = $poll->votes->groupBy('option_id')->map->count();
            $votingOpen = $poll->status === EventPollStatus::Open
                && ($poll->opens_at === null || CarbonImmutable::now('UTC')->greaterThanOrEqualTo(CarbonImmutable::instance($poll->opens_at)->utc()))
                && ($poll->closes_at === null || CarbonImmutable::now('UTC')->lessThan(CarbonImmutable::instance($poll->closes_at)->utc()));
            $showResults = $manager || $poll->status === EventPollStatus::Closed || ($poll->closes_at !== null && CarbonImmutable::now('UTC')->greaterThanOrEqualTo(CarbonImmutable::instance($poll->closes_at)->utc()));

            return [
                'id' => (string) $poll->id,
                'key' => (string) $poll->key,
                'type' => $poll->poll_type->value,
                'questionKey' => $poll->question_key,
                'question' => $poll->question,
                'opensAt' => $poll->opens_at?->toIso8601String(),
                'closesAt' => $poll->closes_at?->toIso8601String(),
                'opensLocal' => $poll->opens_at?->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'closesLocal' => $poll->closes_at?->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'status' => $poll->status->value,
                'votingOpen' => $votingOpen,
                'maxChoices' => (int) $poll->max_choices,
                'selectedOptionIds' => $selected,
                'settings' => $poll->settings ?? [],
                'options' => $poll->options->map(static fn ($option): array => [
                    'id' => (string) $option->id,
                    'label' => (string) $option->label,
                    'value' => (string) $option->value,
                    'metadata' => $option->metadata ?? [],
                    'votes' => $showResults ? (int) ($counts[$option->id] ?? 0) : null,
                ])->all(),
            ];
        })->all());

        return ['phases' => $phaseRows, 'polls' => $pollRows];
    }

    /**
     * Bounded owner summary consumed by EventManagement Event Command composition.
     * Polls are only considered unresolved when a configured poll is still draft/open;
     * absence of a poll is not treated as a missing required poll because Polls owns no
     * generic required-poll policy.
     *
     * @return array{pollCount:int,draftCount:int,openCount:int,closedCount:int,cancelledCount:int,unresolvedCount:int}
     */
    public function commandSummary(EventOccurrence $occurrence): array
    {
        $counts = EventPoll::query()
            ->where('occurrence_id', $occurrence->id)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $draft = (int) ($counts[EventPollStatus::Draft->value] ?? 0);
        $open = (int) ($counts[EventPollStatus::Open->value] ?? 0);
        $closed = (int) ($counts[EventPollStatus::Closed->value] ?? 0);
        $cancelled = (int) ($counts[EventPollStatus::Cancelled->value] ?? 0);

        return [
            'pollCount' => $draft + $open + $closed + $cancelled,
            'draftCount' => $draft,
            'openCount' => $open,
            'closedCount' => $closed,
            'cancelledCount' => $cancelled,
            'unresolvedCount' => $draft + $open,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function management(Event $event): array
    {
        return array_values($event->occurrences
            ->sortBy('starts_at')
            ->values()
            ->map(fn (EventOccurrence $occurrence): array => [
                'occurrenceId' => (string) $occurrence->id,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                ...$this->forOccurrence($occurrence, manager: true),
            ])
            ->all());
    }
}
