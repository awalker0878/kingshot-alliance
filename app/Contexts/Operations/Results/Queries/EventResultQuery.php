<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Queries;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventAllianceResult;
use App\Contexts\Operations\EventCore\Models\EventAllianceResultMetric;
use App\Contexts\Operations\EventCore\Models\EventMetricDefinition;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Models\EventPlayerResult;
use App\Contexts\Operations\EventCore\Models\EventPlayerResultMetric;
use App\Contexts\Operations\EventCore\Models\EventResult;
use App\Contexts\Operations\EventCore\Models\EventResultMetric;
use App\Contexts\Operations\Results\Enums\EventMetricSource;
use Carbon\CarbonImmutable;
use LogicException;

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
        ])->values()->all();

        $rows = $occurrences->map(function (EventOccurrence $occurrence) use ($summaries, $allianceResults, $playerResults, $playerOptions): array {
            $occurrenceId = (string) $occurrence->id;
            $summary = $summaries->get($occurrenceId);
            $allianceRows = $allianceResults->get($occurrenceId, collect());
            $playerRows = $playerResults->get($occurrenceId, collect());

            return [
                'occurrenceId' => $occurrenceId,
                'startsAt' => $this->dateTime($occurrence->getAttribute('starts_at')),
                'summary' => $summary instanceof EventResult ? $this->summary($summary) : null,
                'allianceResults' => $allianceRows
                    ->map(fn (EventAllianceResult $result): array => $this->allianceResult($result))
                    ->values()
                    ->all(),
                'playerResults' => $playerRows
                    ->map(fn (EventPlayerResult $result): array => $this->playerResult($result))
                    ->values()
                    ->all(),
                'players' => $playerOptions,
            ];
        })->all();

        return array_values($rows);
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
            'recordedAt' => $this->nullableDateTime($result->getAttribute('recorded_at')),
        ];
    }

    /** @return array<string,mixed> */
    private function allianceResult(EventAllianceResult $result): array
    {
        return [
            'id' => (string) $result->id,
            'allianceId' => (string) $result->alliance_id,
            'allianceName' => (string) $result->alliance_name_snapshot,
            'allianceTag' => $result->alliance_tag_snapshot,
            'currentAllianceName' => $result->alliance?->name,
            'outcome' => $result->outcome,
            'score' => $result->score,
            'rank' => $result->rank,
            'metrics' => $this->metrics($result->metrics),
            'notes' => $result->notes,
            'recordedAt' => $this->nullableDateTime($result->getAttribute('recorded_at')),
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
            'recordedAt' => $this->nullableDateTime($result->getAttribute('recorded_at')),
        ];
    }

    /**
     * @param  iterable<EventResultMetric|EventAllianceResultMetric|EventPlayerResultMetric>  $metrics
     * @return list<array<string,mixed>>
     */
    private function metrics(iterable $metrics): array
    {
        $rows = [];

        foreach ($metrics as $metric) {
            $definition = $metric->getRelation('definition');
            if (! $definition instanceof EventMetricDefinition) {
                throw new LogicException('Event metric values require a loaded metric definition.');
            }

            $source = $metric->getAttribute('source');
            $sourceEnum = $source instanceof EventMetricSource
                ? $source
                : EventMetricSource::from((string) $source);
            $dimensionKey = (string) $metric->getAttribute('dimension_key');

            $rows[] = [
                'subject' => $definition->subject->value,
                'key' => (string) $definition->key,
                'labelKey' => (string) $definition->label_key,
                'unit' => $definition->unit,
                'valueType' => $definition->value_type->value,
                'aggregation' => $definition->aggregation->value,
                'dimensionKind' => $definition->dimension_kind,
                'dimensionKey' => $dimensionKey === '' ? null : $dimensionKey,
                'isPrimary' => (bool) $definition->is_primary,
                'isContributionMetric' => (bool) $definition->is_contribution_metric,
                'higherIsBetter' => $definition->higher_is_better,
                'value' => (string) $metric->getAttribute('value'),
                'source' => $sourceEnum->value,
                'recordedAt' => $this->nullableDateTime($metric->getAttribute('recorded_at')),
            ];
        }

        return $rows;
    }

    private function dateTime(mixed $value): string
    {
        return CarbonImmutable::parse((string) $value)->toIso8601String();
    }

    private function nullableDateTime(mixed $value): ?string
    {
        return $value === null ? null : $this->dateTime($value);
    }
}
