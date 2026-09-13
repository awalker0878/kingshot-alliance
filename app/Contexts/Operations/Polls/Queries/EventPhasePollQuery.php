<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Polls\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventPhase;
use App\Contexts\Operations\Events\Queries\EventPhaseCatalogueQuery;
use App\Contexts\Operations\Events\Services\EventPhaseService;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Contexts\Operations\Polls\Models\EventPollOption;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class EventPhasePollQuery
{
    public function __construct(
        private EventPhaseService $phases,
        private EventPhaseCatalogueQuery $phaseCatalogue,
        private EventPollCatalogueQuery $pollCatalogue,
    ) {}

    /** @return array<string,mixed> */
    public function forOccurrence(EventOccurrence $occurrence, string $actorId, ?PlayerReference $player = null, bool $manager = false, ?string $phaseCursor = null, ?string $pollCursor = null): array
    {
        $occurrence->loadMissing('event');
        $timezone = (string) $occurrence->event->timezone;
        $phasePage = $this->phaseCatalogue->forOccurrence($occurrence, $actorId, $phaseCursor);
        $phaseRows = array_values($phasePage['items']
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

        $pollPage = $this->pollCatalogue->forOccurrence($occurrence, $actorId, $manager, $pollCursor);
        $pollIds = $pollPage['items']->modelKeys();
        $options = EventPollOption::query()->whereIn('poll_id', $pollIds)->orderBy('poll_id')->orderBy('sort_order')->orderBy('id')
            ->limit(1251)->get();
        $optionsByPoll = $options->groupBy('poll_id');
        if ($options->count() > 1250 || $optionsByPoll->contains(static fn (Collection $rows): bool => $rows->count() > 50)) {
            throw ValidationException::withMessages(['poll' => 'Stored poll options exceed the supported owner budget.']);
        }
        // Aggregate every retained vote in the database; only one row per bounded option returns.
        $votes = DB::table('event_poll_votes as vote')
            ->join('event_poll_options as option', static fn ($join) => $join
                ->on('option.id', '=', 'vote.option_id')->on('option.poll_id', '=', 'vote.poll_id'))
            ->whereIn('vote.poll_id', $pollIds)
            ->selectRaw('vote.poll_id, vote.option_id, COUNT(*) AS aggregate, MAX(CASE WHEN vote.player_id = ? THEN 1 ELSE 0 END) AS selected', [$player->playerId ?? ''])
            ->groupBy('vote.poll_id', 'vote.option_id')->get()
            ->keyBy(static fn (object $vote): string => $vote->poll_id.':'.$vote->option_id);
        $pollRows = array_values($pollPage['items']->map(function (EventPoll $poll) use ($optionsByPoll, $votes, $manager, $timezone): array {
            $options = $optionsByPoll->get((string) $poll->id, new Collection);
            $selected = $options->filter(static fn (EventPollOption $option): bool => (int) ($votes->get($poll->id.':'.$option->id)->selected ?? 0) === 1)
                ->map(static fn (EventPollOption $option): string => (string) $option->id)->values()->all();
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
                'options' => $options->map(static fn (EventPollOption $option): array => [
                    'id' => (string) $option->id,
                    'label' => (string) $option->label,
                    'value' => (string) $option->value,
                    'metadata' => $option->metadata ?? [],
                    'votes' => $showResults ? (int) ($votes->get($poll->id.':'.$option->id)->aggregate ?? 0) : null,
                ])->all(),
            ];
        })->all());

        return ['phases' => $phaseRows, 'polls' => $pollRows,
            'phasePage' => array_diff_key($phasePage, ['items' => true]),
            'pollPage' => array_diff_key($pollPage, ['items' => true])];
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
    public function management(Event $event, string $actorId, ?string $phaseCursor = null, ?string $pollCursor = null): array
    {
        return array_values($event->occurrences
            ->sortBy('starts_at')
            ->values()
            ->map(fn (EventOccurrence $occurrence): array => [
                'occurrenceId' => (string) $occurrence->id,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                ...$this->forOccurrence($occurrence, $actorId, manager: true, phaseCursor: $phaseCursor, pollCursor: $pollCursor),
            ])
            ->all());
    }
}
