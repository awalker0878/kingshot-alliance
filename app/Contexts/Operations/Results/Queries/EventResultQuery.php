<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Queries;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Queries\EventEligiblePlayerQuery;
use App\Contexts\Operations\Results\Enums\EventMetricSource;
use App\Contexts\Operations\Results\Models\EventAllianceResult;
use App\Contexts\Operations\Results\Models\EventAllianceResultMetric;
use App\Contexts\Operations\Results\Models\EventMetricDefinition;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Results\Models\EventPlayerResultMetric;
use App\Contexts\Operations\Results\Models\EventResult;
use App\Contexts\Operations\Results\Models\EventResultMetric;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class EventResultQuery
{
    public function __construct(
        private EventEligiblePlayerQuery $eligiblePlayers,
        private PlayerReferenceQuery $players,
        private AllianceReferenceQuery $alliances,
    ) {}

    /** @return array{summary:?array<string,mixed>,player:?array<string,mixed>} */
    public function forOccurrence(EventOccurrence $occurrence, ?PlayerReference $player): array
    {
        $summary = EventResult::query()->where('occurrence_id', $occurrence->id)->with('metrics.definition')->first();
        $playerResult = $player instanceof PlayerReference
            ? EventPlayerResult::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('player_id', $player->playerId)
                ->with('metrics.definition')
                ->first()
            : null;

        return [
            'summary' => $summary instanceof EventResult ? $this->summary($summary) : null,
            'player' => $playerResult instanceof EventPlayerResult ? $this->playerResult($playerResult, $player) : null,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function management(Event $event): array
    {
        $eligible = $this->eligiblePlayers->for($event)->keyBy(static fn (PlayerReference $player): string => $player->playerId);
        $occurrences = $event->occurrences->sortBy('starts_at')->values();
        $occurrenceIds = $occurrences->pluck('id');
        $summaries = EventResult::query()->whereIn('occurrence_id', $occurrenceIds)->with('metrics.definition')->get()
            ->keyBy(static fn (EventResult $result): string => (string) $result->occurrence_id);
        $allianceResults = EventAllianceResult::query()->whereIn('occurrence_id', $occurrenceIds)->with('metrics.definition')
            ->orderByDesc('score')->orderBy('rank')->get()
            ->groupBy(static fn (EventAllianceResult $result): string => (string) $result->occurrence_id);
        $playerResults = EventPlayerResult::query()->whereIn('occurrence_id', $occurrenceIds)->with('metrics.definition')
            ->orderByDesc('score')->orderBy('rank')->get()
            ->groupBy(static fn (EventPlayerResult $result): string => (string) $result->occurrence_id);

        $resultPlayerIds = $playerResults->flatten(1)->pluck('player_id')->map(static fn ($id): string => (string) $id)->all();
        $playerReferences = $this->players->byIds(array_merge($eligible->keys()->all(), $resultPlayerIds));
        $playerOptions = $eligible->values()->map(static fn (PlayerReference $player): array => [
            'id' => $player->playerId,
            'name' => $player->currentName,
        ])->values()->all();

        $rows = $occurrences->map(function (EventOccurrence $occurrence) use ($summaries, $allianceResults, $playerResults, $playerOptions, $playerReferences): array {
            $occurrenceId = (string) $occurrence->id;
            $summary = $summaries->get($occurrenceId);
            $allianceRows = $allianceResults->get($occurrenceId, collect());
            $playerRows = $playerResults->get($occurrenceId, collect());

            return [
                'occurrenceId' => $occurrenceId,
                'startsAt' => $this->dateTime($occurrence->getAttribute('starts_at')),
                'summary' => $summary instanceof EventResult ? $this->summary($summary) : null,
                'allianceResults' => $allianceRows->map(fn (EventAllianceResult $result): array => $this->allianceResult($result))->values()->all(),
                'playerResults' => $playerRows->map(fn (EventPlayerResult $result): array => $this->playerResult(
                    $result,
                    $playerReferences[(string) $result->player_id] ?? null,
                ))->values()->all(),
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
        $current = $this->alliances->find((string) $result->alliance_id);

        return [
            'id' => (string) $result->id,
            'allianceId' => (string) $result->alliance_id,
            'allianceName' => (string) $result->alliance_name_snapshot,
            'allianceTag' => $result->alliance_tag_snapshot,
            'currentAllianceName' => $current?->name,
            'outcome' => $result->outcome,
            'score' => $result->score,
            'rank' => $result->rank,
            'metrics' => $this->metrics($result->metrics),
            'notes' => $result->notes,
            'recordedAt' => $this->nullableDateTime($result->getAttribute('recorded_at')),
        ];
    }

    /** @return array<string,mixed> */
    private function playerResult(EventPlayerResult $result, ?PlayerReference $player): array
    {
        return [
            'id' => (string) $result->id,
            'playerId' => (string) $result->player_id,
            'playerName' => $player?->currentName,
            'outcome' => $result->outcome,
            'score' => $result->score,
            'rank' => $result->rank,
            'metrics' => $this->metrics($result->metrics),
            'notes' => $result->notes,
            'recordedAt' => $this->nullableDateTime($result->getAttribute('recorded_at')),
        ];
    }

    /**
     * @param iterable<EventResultMetric|EventAllianceResultMetric|EventPlayerResultMetric> $metrics
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
            $sourceEnum = $source instanceof EventMetricSource ? $source : EventMetricSource::from((string) $source);
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
