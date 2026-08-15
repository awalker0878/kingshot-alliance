<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\EventAnalysis\Queries;

use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\Results\Models\EventAllianceResult;
use App\Contexts\Operations\Results\Models\EventAllianceResultMetric;
use App\Contexts\Operations\Results\Models\EventMetricDefinition;
use App\Contexts\Operations\Participation\Models\EventPlayerContext;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Results\Models\EventPlayerResultMetric;
use App\Contexts\Operations\Results\Models\EventResult;
use App\Contexts\Operations\Results\Models\EventResultMetric;
use App\Contexts\Operations\Results\Enums\EventMetricSource;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class EventOrganizationHistoryQuery
{
    public function __construct(private readonly EventOrganizationEvidenceQuery $evidence) {}

    /**
     * @param  array{event_type_slug?:string|null,from?:DateTimeInterface|null,until?:DateTimeInterface|null,limit?:int|null}  $filters
     * @return list<array<string,mixed>>
     */
    public function forTarget(EventScope $scope, string $targetId, array $filters = []): array
    {
        if (! in_array($scope, [EventScope::Alliance, EventScope::Kingdom], true)) {
            throw new InvalidArgumentException('Organization Event history supports Alliance or Kingdom targets only.');
        }

        $targetColumn = $scope === EventScope::Alliance ? 'event.alliance_id' : 'event.kingdom_id';
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));

        $rows = DB::table('event_occurrences as occurrence')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->join('event_type_scopes as type_scope', 'type_scope.id', '=', 'event.event_type_scope_id')
            ->where('event.scope', $scope->value)
            ->where($targetColumn, $targetId)
            ->when(
                isset($filters['event_type_slug']) && trim((string) $filters['event_type_slug']) !== '',
                static fn (Builder $query) => $query->where('event_type.slug', trim((string) $filters['event_type_slug'])),
            )
            ->when(
                ($filters['from'] ?? null) instanceof DateTimeInterface,
                static fn (Builder $query) => $query->where('occurrence.starts_at', '>=', $filters['from']),
            )
            ->when(
                ($filters['until'] ?? null) instanceof DateTimeInterface,
                static fn (Builder $query) => $query->where('occurrence.starts_at', '<=', $filters['until']),
            )
            ->orderByDesc('occurrence.starts_at')
            ->orderByDesc('occurrence.id')
            ->limit($limit)
            ->get([
                'occurrence.id as occurrence_id',
                'occurrence.starts_at',
                'occurrence.ends_at',
                'occurrence.status as occurrence_status',
                'event.id as event_id',
                'event.target_display_name',
                'event.target_secondary_label',
                'event.title',
                'event_type.slug as event_type_slug',
                'event_type.name_key as event_type_name_key',
                'type_scope.result_score_label_key',
                'type_scope.result_score_unit',
                'type_scope.result_score_higher_is_better',
            ]);

        $occurrenceIds = $rows->pluck('occurrence_id')->map(static fn ($id): string => (string) $id);
        if ($occurrenceIds->isEmpty()) {
            return [];
        }

        $operationalEvidence = $this->evidence->forOccurrences($occurrenceIds);

        $results = EventResult::query()
            ->whereIn('occurrence_id', $occurrenceIds)
            ->with('metrics.definition')
            ->get()
            ->keyBy(static fn (EventResult $result): string => (string) $result->occurrence_id);

        $contexts = EventPlayerContext::query()
            ->whereIn('occurrence_id', $occurrenceIds)
            ->orderBy('player_name_snapshot')
            ->get()
            ->groupBy(static fn (EventPlayerContext $context): string => (string) $context->occurrence_id);

        $playerResults = EventPlayerResult::query()
            ->whereIn('occurrence_id', $occurrenceIds)
            ->with('metrics.definition')
            ->get()
            ->groupBy(static fn (EventPlayerResult $result): string => (string) $result->occurrence_id);

        $allianceResults = $scope === EventScope::Kingdom
            ? EventAllianceResult::query()
                ->whereIn('occurrence_id', $occurrenceIds)
                ->with('metrics.definition')
                ->get()
                ->groupBy(static fn (EventAllianceResult $result): string => (string) $result->occurrence_id)
            : null;

        $history = [];
        foreach ($rows as $row) {
            $occurrenceId = (string) $row->occurrence_id;
            $eventResult = $results->get($occurrenceId);
            $resultByPlayerId = [];
            foreach ($playerResults->get($occurrenceId, collect()) as $playerResult) {
                if ($playerResult instanceof EventPlayerResult) {
                    $resultByPlayerId[(string) $playerResult->player_id] = $playerResult;
                }
            }

            $participants = [];
            foreach ($contexts->get($occurrenceId, collect()) as $context) {
                if (! $context instanceof EventPlayerContext) {
                    continue;
                }

                $playerResult = $resultByPlayerId[(string) $context->player_id] ?? null;
                $participants[] = [
                    'playerId' => (string) $context->player_id,
                    'playerName' => (string) $context->player_name_snapshot,
                    'kingdomIdAtEvent' => (string) $context->kingdom_id_at_event,
                    'representedAllianceId' => $context->represented_alliance_id === null ? null : (string) $context->represented_alliance_id,
                    'representedAllianceName' => $context->represented_alliance_name_snapshot,
                    'representedAllianceTag' => $context->represented_alliance_tag_snapshot,
                    'contextFrozenAt' => CarbonImmutable::parse((string) $context->context_frozen_at)->toIso8601String(),
                    'result' => $playerResult instanceof EventPlayerResult ? $this->playerResultPayload($playerResult) : null,
                ];
            }

            $organizationAllianceResults = [];
            if ($scope === EventScope::Kingdom && $allianceResults !== null) {
                foreach ($allianceResults->get($occurrenceId, collect()) as $allianceResult) {
                    if ($allianceResult instanceof EventAllianceResult) {
                        $organizationAllianceResults[] = $this->allianceResultPayload($allianceResult);
                    }
                }
            }

            $history[] = [
                'occurrenceId' => $occurrenceId,
                'eventId' => (string) $row->event_id,
                'eventType' => [
                    'slug' => (string) $row->event_type_slug,
                    'nameKey' => (string) $row->event_type_name_key,
                ],
                'scope' => $scope->value,
                'targetId' => $targetId,
                'targetDisplayName' => (string) $row->target_display_name,
                'targetSecondaryLabel' => $row->target_secondary_label === null ? null : (string) $row->target_secondary_label,
                'title' => $row->title === null ? null : (string) $row->title,
                'startsAt' => (string) $row->starts_at,
                'endsAt' => (string) $row->ends_at,
                'occurrenceStatus' => (string) $row->occurrence_status,
                'scoreSemantics' => [
                    'labelKey' => (string) $row->result_score_label_key,
                    'unit' => $row->result_score_unit === null ? null : (string) $row->result_score_unit,
                    'higherIsBetter' => (bool) $row->result_score_higher_is_better,
                ],
                'result' => $eventResult instanceof EventResult ? $this->eventResultPayload($eventResult) : null,
                'evidence' => $operationalEvidence[$occurrenceId] ?? [
                    'attendance' => ['total' => 0, 'byStatus' => []],
                    'roster' => ['total' => 0, 'byStatus' => []],
                    'rallies' => ['total' => 0, 'byStatus' => []],
                    'objectives' => ['total' => 0, 'assignments' => 0, 'byStatus' => []],
                ],
                'participants' => $participants,
                'allianceResults' => $organizationAllianceResults,
            ];
        }

        return $history;
    }

    /** @return array<string,mixed> */
    private function eventResultPayload(EventResult $result): array
    {
        $metrics = [];
        foreach ($result->metrics as $metric) {
            if ($metric instanceof EventResultMetric) {
                $metrics[] = $this->metricPayload($metric);
            }
        }

        return [
            'outcome' => $result->outcome,
            'score' => $result->score,
            'opponentScore' => $result->opponent_score,
            'rank' => $result->rank,
            'recordedAt' => CarbonImmutable::parse((string) $result->recorded_at)->toIso8601String(),
            'metrics' => $metrics,
        ];
    }

    /** @return array<string,mixed> */
    private function playerResultPayload(EventPlayerResult $result): array
    {
        $metrics = [];
        foreach ($result->metrics as $metric) {
            if ($metric instanceof EventPlayerResultMetric) {
                $metrics[] = $this->metricPayload($metric);
            }
        }

        return [
            'outcome' => $result->outcome,
            'score' => $result->score,
            'rank' => $result->rank,
            'recordedAt' => CarbonImmutable::parse((string) $result->recorded_at)->toIso8601String(),
            'metrics' => $metrics,
        ];
    }

    /** @return array<string,mixed> */
    private function allianceResultPayload(EventAllianceResult $result): array
    {
        $metrics = [];
        foreach ($result->metrics as $metric) {
            if ($metric instanceof EventAllianceResultMetric) {
                $metrics[] = $this->metricPayload($metric);
            }
        }

        return [
            'allianceId' => (string) $result->alliance_id,
            'allianceName' => (string) $result->alliance_name_snapshot,
            'allianceTag' => $result->alliance_tag_snapshot,
            'outcome' => $result->outcome,
            'score' => $result->score,
            'rank' => $result->rank,
            'recordedAt' => CarbonImmutable::parse((string) $result->recorded_at)->toIso8601String(),
            'metrics' => $metrics,
        ];
    }

    /** @return array<string,mixed> */
    private function metricPayload(EventResultMetric|EventPlayerResultMetric|EventAllianceResultMetric $metric): array
    {
        $definition = $metric->definition;
        if (! $definition instanceof EventMetricDefinition) {
            throw new LogicException('Event result metric must reference a metric definition.');
        }

        return [
            'key' => (string) $definition->key,
            'labelKey' => (string) $definition->label_key,
            'unit' => $definition->unit,
            'dimensionKey' => $metric->dimension_key === '' ? null : $metric->dimension_key,
            'value' => $metric->value,
            'source' => EventMetricSource::from((string) $metric->getRawOriginal('source'))->value,
        ];
    }
}
