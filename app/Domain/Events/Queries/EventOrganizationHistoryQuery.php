<?php

declare(strict_types=1);

namespace App\Domain\Events\Queries;

use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventAllianceResult;
use App\Domain\Events\Models\EventPlayerContext;
use App\Domain\Events\Models\EventPlayerResult;
use App\Domain\Events\Models\EventResult;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EventOrganizationHistoryQuery
{
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
            : collect();

        return $rows->map(function (object $row) use ($scope, $targetId, $results, $contexts, $playerResults, $allianceResults): array {
            $occurrenceId = (string) $row->occurrence_id;
            $result = $results->get($occurrenceId);
            $occurrenceContexts = $contexts->get($occurrenceId, collect());
            $occurrencePlayerResults = $playerResults->get($occurrenceId, collect())
                ->keyBy(static fn (EventPlayerResult $playerResult): string => (string) $playerResult->player_id);

            return [
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
                'result' => $result instanceof EventResult ? $this->eventResultPayload($result) : null,
                'participants' => $occurrenceContexts
                    ->map(function (EventPlayerContext $context) use ($occurrencePlayerResults): array {
                        $playerResult = $occurrencePlayerResults->get((string) $context->player_id);

                        return [
                            'playerId' => (string) $context->player_id,
                            'playerName' => (string) $context->player_name_snapshot,
                            'kingdomIdAtEvent' => (string) $context->kingdom_id_at_event,
                            'representedAllianceId' => $context->represented_alliance_id === null ? null : (string) $context->represented_alliance_id,
                            'representedAllianceName' => $context->represented_alliance_name_snapshot,
                            'representedAllianceTag' => $context->represented_alliance_tag_snapshot,
                            'contextFrozenAt' => $context->context_frozen_at?->toIso8601String(),
                            'result' => $playerResult instanceof EventPlayerResult
                                ? $this->playerResultPayload($playerResult)
                                : null,
                        ];
                    })
                    ->values()
                    ->all(),
                'allianceResults' => $scope === EventScope::Kingdom
                    ? $allianceResults->get($occurrenceId, collect())
                        ->map(fn (EventAllianceResult $allianceResult): array => $this->allianceResultPayload($allianceResult))
                        ->values()
                        ->all()
                    : [],
            ];
        })->values()->all();
    }

    /** @return array<string,mixed> */
    private function eventResultPayload(EventResult $result): array
    {
        return [
            'outcome' => $result->outcome,
            'score' => $result->score,
            'opponentScore' => $result->opponent_score,
            'rank' => $result->rank,
            'recordedAt' => $result->recorded_at?->toIso8601String(),
            'metrics' => $result->metrics->map(static fn ($metric): array => [
                'key' => (string) $metric->definition->key,
                'labelKey' => (string) $metric->definition->label_key,
                'unit' => $metric->definition->unit,
                'dimensionKey' => $metric->dimension_key === '' ? null : $metric->dimension_key,
                'value' => $metric->value,
                'source' => $metric->source->value,
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function playerResultPayload(EventPlayerResult $result): array
    {
        return [
            'outcome' => $result->outcome,
            'score' => $result->score,
            'rank' => $result->rank,
            'recordedAt' => $result->recorded_at?->toIso8601String(),
            'metrics' => $result->metrics->map(static fn ($metric): array => [
                'key' => (string) $metric->definition->key,
                'labelKey' => (string) $metric->definition->label_key,
                'unit' => $metric->definition->unit,
                'dimensionKey' => $metric->dimension_key === '' ? null : $metric->dimension_key,
                'value' => $metric->value,
                'source' => $metric->source->value,
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function allianceResultPayload(EventAllianceResult $result): array
    {
        return [
            'allianceId' => (string) $result->alliance_id,
            'allianceName' => (string) $result->alliance_name_snapshot,
            'allianceTag' => $result->alliance_tag_snapshot,
            'outcome' => $result->outcome,
            'score' => $result->score,
            'rank' => $result->rank,
            'recordedAt' => $result->recorded_at?->toIso8601String(),
            'metrics' => $result->metrics->map(static fn ($metric): array => [
                'key' => (string) $metric->definition->key,
                'labelKey' => (string) $metric->definition->label_key,
                'unit' => $metric->definition->unit,
                'dimensionKey' => $metric->dimension_key === '' ? null : $metric->dimension_key,
                'value' => $metric->value,
                'source' => $metric->source->value,
            ])->values()->all(),
        ];
    }
}
