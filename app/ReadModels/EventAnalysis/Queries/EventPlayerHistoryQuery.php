<?php

declare(strict_types=1);

namespace App\ReadModels\EventAnalysis\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Results\Enums\EventMetricSource;
use App\Contexts\Operations\Results\Models\EventMetricDefinition;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Results\Models\EventPlayerResultMetric;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class EventPlayerHistoryQuery
{
    public function __construct(private EventPlayerOccurrenceEvidenceQuery $evidence) {}

    /**
     * @param array{
     *   scope?:string|null,
     *   event_type_slug?:string|null,
     *   from?:DateTimeInterface|null,
     *   until?:DateTimeInterface|null,
     *   metric_key?:string|null,
     *   represented_alliance_id?:string|null,
     *   kingdom_id_at_event?:string|null,
     *   participation_outcome?:string|null,
     *   limit?:int|null
     * } $filters
     * @return list<array<string,mixed>>
     */
    public function forPlayer(PlayerReference $player, array $filters = []): array
    {
        $scope = isset($filters['scope']) ? trim((string) $filters['scope']) : null;
        $validScopes = array_map(static fn (EventScope $case): string => $case->value, EventScope::cases());
        if ($scope !== null && $scope !== '' && ! in_array($scope, $validScopes, true)) {
            throw ValidationException::withMessages([
                'scope' => 'Event scope must be player, alliance, or kingdom.',
            ]);
        }

        $outcome = isset($filters['participation_outcome'])
            ? trim((string) $filters['participation_outcome'])
            : null;
        if ($outcome !== null && $outcome !== '' && ! in_array($outcome, ['completed', 'absent', 'excused', 'unresolved'], true)) {
            throw ValidationException::withMessages([
                'participation_outcome' => 'Participation outcome must be completed, absent, excused, or unresolved.',
            ]);
        }

        $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
        $query = DB::table('event_player_contexts as context')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'context.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->join('event_type_scopes as type_scope', 'type_scope.id', '=', 'event.event_type_scope_id')
            ->where('context.player_id', $player->playerId)
            ->when(
                $scope !== null && $scope !== '',
                static fn (Builder $q) => $q->where('event.scope', $scope),
            )
            ->when(
                isset($filters['event_type_slug']) && trim((string) $filters['event_type_slug']) !== '',
                static fn (Builder $q) => $q->where('event_type.slug', trim((string) $filters['event_type_slug'])),
            )
            ->when(
                ($filters['from'] ?? null) instanceof DateTimeInterface,
                static fn (Builder $q) => $q->where('occurrence.starts_at', '>=', $filters['from']),
            )
            ->when(
                ($filters['until'] ?? null) instanceof DateTimeInterface,
                static fn (Builder $q) => $q->where('occurrence.starts_at', '<=', $filters['until']),
            )
            ->when(
                isset($filters['represented_alliance_id']) && trim((string) $filters['represented_alliance_id']) !== '',
                static fn (Builder $q) => $q->where('context.represented_alliance_id', trim((string) $filters['represented_alliance_id'])),
            )
            ->when(
                isset($filters['kingdom_id_at_event']) && trim((string) $filters['kingdom_id_at_event']) !== '',
                static fn (Builder $q) => $q->where('context.kingdom_id_at_event', trim((string) $filters['kingdom_id_at_event'])),
            );

        if ($outcome !== null && $outcome !== '') {
            $this->evidence->applyOutcomeFilter($query, $player, $outcome);
        }

        $metricKey = isset($filters['metric_key']) ? trim((string) $filters['metric_key']) : '';
        if ($metricKey !== '') {
            $query->whereExists(static function (Builder $subquery) use ($player, $metricKey): void {
                $subquery->selectRaw('1')
                    ->from('event_player_results as player_result')
                    ->join('event_player_result_metrics as player_metric', 'player_metric.event_player_result_id', '=', 'player_result.id')
                    ->join('event_metric_definitions as metric_definition', 'metric_definition.id', '=', 'player_metric.metric_definition_id')
                    ->whereColumn('player_result.occurrence_id', 'context.occurrence_id')
                    ->where('player_result.player_id', $player->playerId)
                    ->where('metric_definition.key', $metricKey);
            });
        }

        $rows = $query
            ->orderByDesc('occurrence.starts_at')
            ->orderByDesc('context.id')
            ->limit($limit)
            ->get([
                'context.id as context_id',
                'context.occurrence_id',
                'context.player_id',
                'context.player_name_snapshot',
                'context.represented_alliance_id',
                'context.represented_alliance_name_snapshot',
                'context.represented_alliance_tag_snapshot',
                'context.kingdom_id_at_event',
                'context.context_frozen_at',
                'occurrence.starts_at',
                'occurrence.ends_at',
                'occurrence.status as occurrence_status',
                'event.id as event_id',
                'event.scope',
                'event.player_id as target_player_id',
                'event.alliance_id as target_alliance_id',
                'event.kingdom_id as target_kingdom_id',
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
        $evidence = $this->evidence->forPlayer($player, $occurrenceIds);
        $results = $this->results($player, $occurrenceIds);
        $history = [];

        foreach ($rows as $row) {
            $occurrenceId = (string) $row->occurrence_id;
            $scopeValue = (string) $row->scope;
            $targetId = match ($scopeValue) {
                EventScope::Player->value => $row->target_player_id === null ? null : (string) $row->target_player_id,
                EventScope::Alliance->value => $row->target_alliance_id === null ? null : (string) $row->target_alliance_id,
                EventScope::Kingdom->value => $row->target_kingdom_id === null ? null : (string) $row->target_kingdom_id,
                default => throw new LogicException('Event history row has an unsupported scope.'),
            };
            $participation = $evidence[$occurrenceId] ?? [
                'committed' => false,
                'completed' => false,
                'absent' => false,
                'excused' => false,
                'unresolved' => false,
                'outcome' => null,
            ];
            $result = $results->get($occurrenceId);

            $history[] = [
                'occurrenceId' => $occurrenceId,
                'eventId' => (string) $row->event_id,
                'eventType' => [
                    'slug' => (string) $row->event_type_slug,
                    'nameKey' => (string) $row->event_type_name_key,
                ],
                'scope' => $scopeValue,
                'targetId' => $targetId,
                'target' => [
                    'playerId' => $row->target_player_id === null ? null : (string) $row->target_player_id,
                    'allianceId' => $row->target_alliance_id === null ? null : (string) $row->target_alliance_id,
                    'kingdomId' => $row->target_kingdom_id === null ? null : (string) $row->target_kingdom_id,
                    'displayName' => (string) $row->target_display_name,
                    'secondaryLabel' => $row->target_secondary_label === null ? null : (string) $row->target_secondary_label,
                ],
                'title' => $row->title === null ? null : (string) $row->title,
                'startsAt' => (string) $row->starts_at,
                'endsAt' => (string) $row->ends_at,
                'occurrenceStatus' => (string) $row->occurrence_status,
                'playerContext' => [
                    'playerId' => (string) $row->player_id,
                    'playerName' => (string) $row->player_name_snapshot,
                    'kingdomIdAtEvent' => (string) $row->kingdom_id_at_event,
                    'representedAllianceId' => $row->represented_alliance_id === null ? null : (string) $row->represented_alliance_id,
                    'representedAllianceName' => $row->represented_alliance_name_snapshot === null ? null : (string) $row->represented_alliance_name_snapshot,
                    'representedAllianceTag' => $row->represented_alliance_tag_snapshot === null ? null : (string) $row->represented_alliance_tag_snapshot,
                    'frozenAt' => (string) $row->context_frozen_at,
                ],
                'participation' => $participation,
                'scoreSemantics' => [
                    'labelKey' => (string) $row->result_score_label_key,
                    'unit' => $row->result_score_unit === null ? null : (string) $row->result_score_unit,
                    'higherIsBetter' => (bool) $row->result_score_higher_is_better,
                ],
                'result' => $result instanceof EventPlayerResult ? $this->resultPayload($result) : null,
            ];
        }

        return $history;
    }

    /**
     * @param  Collection<int,string>  $occurrenceIds
     * @return Collection<string,EventPlayerResult>
     */
    private function results(PlayerReference $player, Collection $occurrenceIds): Collection
    {
        if ($occurrenceIds->isEmpty()) {
            return collect();
        }

        return EventPlayerResult::query()
            ->where('player_id', $player->playerId)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->with('metrics.definition')
            ->get()
            ->keyBy(static fn (EventPlayerResult $result): string => (string) $result->occurrence_id);
    }

    /** @return array<string,mixed> */
    private function resultPayload(EventPlayerResult $result): array
    {
        $metrics = [];
        foreach ($result->metrics as $metric) {
            if (! $metric instanceof EventPlayerResultMetric) {
                continue;
            }

            $definition = $metric->definition;
            if (! $definition instanceof EventMetricDefinition) {
                throw new LogicException('Event Player result metric must reference a metric definition.');
            }

            $metrics[] = [
                'key' => (string) $definition->key,
                'labelKey' => (string) $definition->label_key,
                'unit' => $definition->unit,
                'valueType' => $definition->value_type->value,
                'aggregation' => $definition->aggregation->value,
                'dimensionKind' => $definition->dimension_kind,
                'dimensionKey' => $metric->dimension_key === '' ? null : $metric->dimension_key,
                'value' => $metric->value,
                'source' => EventMetricSource::from((string) $metric->getRawOriginal('source'))->value,
                'recordedAt' => CarbonImmutable::parse((string) $metric->recorded_at)->toIso8601String(),
            ];
        }

        return [
            'outcome' => $result->outcome,
            'score' => $result->score,
            'rank' => $result->rank,
            'recordedAt' => CarbonImmutable::parse((string) $result->recorded_at)->toIso8601String(),
            'metrics' => $metrics,
        ];
    }
}
