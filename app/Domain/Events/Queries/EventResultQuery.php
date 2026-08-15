<?php

declare(strict_types=1);

namespace App\Domain\Events\Queries;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventAllianceResult;
use App\Domain\Events\Models\EventAllianceResultMetric;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventPlayerResult;
use App\Domain\Events\Models\EventPlayerResultMetric;
use App\Domain\Events\Models\EventResult;
use App\Domain\Events\Models\EventResultMetric;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final readonly class EventResultQuery
{
    public function __construct(private EventEligiblePlayerQuery $eligiblePlayers) {}

    /** @return array{summary:?array<string,mixed>,player:?array<string,mixed>} */
    public function forOccurrence(EventOccurrence $occurrence, ?Player $player): array
    {
        $summary = EventResult::query()
            ->where('occurrence_id', $occurrence->id)
            ->with('metrics.definition')
            ->first();
        $playerResult = $player instanceof Player
            ? EventPlayerResult::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('player_id', $player->id)
                ->with(['player', 'metrics.definition'])
                ->first()
            : null;

        return [
            'summary' => $summary instanceof EventResult ? $this->summary($summary) : null,
            'player' => $playerResult instanceof EventPlayerResult ? $this->playerResult($playerResult) : null,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function management(Event $event): array
    {
        $players = $this->eligiblePlayers->for($event)->keyBy(static fn (Player $player): string => (string) $player->id);
        $occurrences = $event->occurrences->sortBy('starts_at')->values();
        $occurrenceIds = $occurrences->pluck('id');
        $summaries = EventResult::query()
            ->whereIn('occurrence_id', $occurrenceIds)
            ->with('metrics.definition')
            ->get()
            ->keyBy(static fn (EventResult $result): string => (string) $result->occurrence_id);
        $allianceResults = EventAllianceResult::query()
            ->whereIn('occurrence_id', $occurrenceIds)
            ->with(['alliance', 'metrics.definition'])
            ->orderByDesc('score')
            ->orderBy('rank')
            ->get()
            ->groupBy(static fn (EventAllianceResult $result): string => (string) $result->occurrence_id);
        $playerResults = EventPlayerResult::query()
            ->whereIn('occurrence_id', $occurrenceIds)
            ->with(['player', 'metrics.definition'])
            ->orderByDesc('score')
            ->orderBy('rank')
            ->get()
            ->groupBy(static fn (EventPlayerResult $result): string => (string) $result->occurrence_id);
        $playerOptions = $players->values()->map(static fn (Player $player): array => [
            'id' => (string) $player->id,
            'name' => (string) $player->current_name,
        ])->all();

        return $occurrences->map(function (EventOccurrence $occurrence) use ($summaries, $allianceResults, $playerResults, $playerOptions): array {
            $occurrenceId = (string) $occurrence->id;
            $summary = $summaries->get($occurrenceId);
            $allianceRows = $allianceResults->get($occurrenceId, collect());
            $playerRows = $playerResults->get($occurrenceId, collect());

            return [
                'occurrenceId' => $occurrenceId,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'summary' => $summary instanceof EventResult ? $this->summary($summary) : null,
                'allianceResults' => $allianceRows->map(fn (EventAllianceResult $result): array => $this->allianceResult($result))->all(),
                'playerResults' => $playerRows->map(fn (EventPlayerResult $result): array => $this->playerResult($result))->all(),
                'players' => $playerOptions,
            ];
        })->all();
    }

    /** @return array<string,mixed> */
    private function summary(EventResult $result): array
    {
        return [
            'id' => (string) $result->id,
            'outcome' => $result->outcome,
            'score' => $result->score,
            'opponentScore' => $result->opponent_score,
            'rank' => $result->rank,
            'metrics' => $this->metrics($result->metrics),
            'notes' => $result->notes,
            'recordedAt' => $result->recorded_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function allianceResult(EventAllianceResult $result): array
    {
        return [
            'id' => (string) $result->id,
            'allianceId' => (string) $result->alliance_id,
            'allianceName' => (string) $result->alliance_name_snapshot,
            'currentAllianceName' => $result->alliance?->name,
            'outcome' => $result->outcome,
            'score' => $result->score,
            'rank' => $result->rank,
            'metrics' => $this->metrics($result->metrics),
            'notes' => $result->notes,
            'recordedAt' => $result->recorded_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function playerResult(EventPlayerResult $result): array
    {
        return [
            'id' => (string) $result->id,
            'playerId' => (string) $result->player_id,
            'playerName' => $result->player?->current_name,
            'outcome' => $result->outcome,
            'score' => $result->score,
            'rank' => $result->rank,
            'metrics' => $this->metrics($result->metrics),
            'notes' => $result->notes,
            'recordedAt' => $result->recorded_at?->toIso8601String(),
        ];
    }

    /**
     * @param EloquentCollection<int, EventResultMetric|EventAllianceResultMetric|EventPlayerResultMetric> $metrics
     * @return list<array<string,mixed>>
     */
    private function metrics(EloquentCollection $metrics): array
    {
        return $metrics->map(static fn (EventResultMetric|EventAllianceResultMetric|EventPlayerResultMetric $metric): array => [
            'key' => $metric->definition?->key,
            'labelKey' => $metric->definition?->label_key,
            'unit' => $metric->definition?->unit,
            'valueType' => $metric->definition?->value_type->value,
            'aggregation' => $metric->definition?->aggregation->value,
            'dimensionKey' => $metric->dimension_key === '' ? null : $metric->dimension_key,
            'value' => $metric->value,
            'source' => $metric->source->value,
            'recordedAt' => $metric->recorded_at?->toIso8601String(),
        ])->values()->all();
    }
}
