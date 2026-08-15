<?php

declare(strict_types=1);

namespace App\Domain\Events\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Services\EventAuthorization;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cross-occurrence Event contribution intelligence.
 *
 * Compatibility is deliberately narrow: samples are compared only when they
 * share an EventTypeScope and metric identity (including a dimension key when
 * present). This service never creates a universal cross-Event contribution
 * score.
 */
final readonly class EventContributionIntelligenceQuery
{
    public function __construct(
        private EventAuthorization $authorization,
        private EventPlayerHistorySummaryQuery $playerSummary,
    ) {}

    /**
     * @param  array{event_type_slug?:string|null,metric_key?:string|null,from?:DateTimeInterface|null,until?:DateTimeInterface|null,max_samples?:int|null}  $filters
     * @return array<string,mixed>
     */
    public function forPlayer(Player $player, array $filters = []): array
    {
        $samples = $this->samples(
            static fn (Builder $query): Builder => $query->where('context.player_id', $player->id),
            $filters,
        );

        return [
            'scope' => 'player',
            'targetId' => (string) $player->id,
            'participation' => $this->playerSummary->forPlayer($player),
            ...$this->summarize($samples, includeLeaderboards: false),
        ];
    }

    /**
     * @param  array{event_type_slug?:string|null,metric_key?:string|null,from?:DateTimeInterface|null,until?:DateTimeInterface|null,max_samples?:int|null}  $filters
     * @return array<string,mixed>
     */
    public function forAlliance(Player $actor, Alliance $alliance, array $filters = []): array
    {
        $this->authorization->authorize(
            $actor,
            EventScope::Alliance,
            $alliance,
            PermissionKey::EventAllianceView,
        );

        $samples = $this->samples(
            static fn (Builder $query): Builder => $query
                ->where('event.scope', EventScope::Alliance->value)
                ->where('event.alliance_id', $alliance->id),
            $filters,
        );

        return [
            'scope' => EventScope::Alliance->value,
            'targetId' => (string) $alliance->id,
            ...$this->summarize($samples, includeLeaderboards: true),
        ];
    }

    /**
     * @param  array{event_type_slug?:string|null,metric_key?:string|null,from?:DateTimeInterface|null,until?:DateTimeInterface|null,max_samples?:int|null}  $filters
     * @return array<string,mixed>
     */
    public function forKingdom(Player $actor, Kingdom $kingdom, array $filters = []): array
    {
        $this->authorization->authorize(
            $actor,
            EventScope::Kingdom,
            $kingdom,
            PermissionKey::EventKingdomView,
        );

        $samples = $this->samples(
            static fn (Builder $query): Builder => $query
                ->where('event.scope', EventScope::Kingdom->value)
                ->where('event.kingdom_id', $kingdom->id),
            $filters,
        );

        return [
            'scope' => EventScope::Kingdom->value,
            'targetId' => (string) $kingdom->id,
            ...$this->summarize($samples, includeLeaderboards: true),
        ];
    }

    /**
     * @param  callable(Builder):Builder  $target
     * @param  array{event_type_slug?:string|null,metric_key?:string|null,from?:DateTimeInterface|null,until?:DateTimeInterface|null,max_samples?:int|null}  $filters
     * @return list<array<string,mixed>>
     */
    private function samples(callable $target, array $filters): array
    {
        $limit = max(1, min(10000, (int) ($filters['max_samples'] ?? 5000)));
        $metricKey = isset($filters['metric_key']) ? trim((string) $filters['metric_key']) : '';

        $scoreQuery = DB::table('event_player_results as result')
            ->join('event_player_contexts as context', function ($join): void {
                $join->on('context.occurrence_id', '=', 'result.occurrence_id')
                    ->on('context.player_id', '=', 'result.player_id');
            })
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->join('event_type_scopes as type_scope', 'type_scope.id', '=', 'event.event_type_scope_id')
            ->whereNotNull('result.score');
        $target($scoreQuery);
        $this->filters($scoreQuery, $filters);

        $scores = $metricKey !== '' && $metricKey !== 'score'
            ? collect()
            : $scoreQuery
                ->orderBy('occurrence.starts_at')
                ->limit($limit)
                ->get([
                    'event.event_type_scope_id',
                    'event_type.slug as event_type_slug',
                    'event.scope as event_scope',
                    'occurrence.id as occurrence_id',
                    'occurrence.starts_at',
                    'result.player_id',
                    'context.player_name_snapshot as player_name',
                    'result.score as value',
                    'type_scope.result_score_label_key as label_key',
                    'type_scope.result_score_unit as unit',
                    'type_scope.result_score_higher_is_better as higher_is_better',
                ]);

        $metricQuery = DB::table('event_player_result_metrics as metric')
            ->join('event_player_results as result', 'result.id', '=', 'metric.event_player_result_id')
            ->join('event_metric_definitions as definition', 'definition.id', '=', 'metric.metric_definition_id')
            ->join('event_player_contexts as context', function ($join): void {
                $join->on('context.occurrence_id', '=', 'result.occurrence_id')
                    ->on('context.player_id', '=', 'result.player_id');
            })
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('definition.subject', 'player')
            ->where('definition.is_contribution_metric', true);
        $target($metricQuery);
        $this->filters($metricQuery, $filters);
        if ($metricKey !== '' && $metricKey !== 'score') {
            $metricQuery->where('definition.key', $metricKey);
        }

        $metrics = $metricKey === 'score'
            ? collect()
            : $metricQuery
                ->orderBy('occurrence.starts_at')
                ->limit($limit)
                ->get([
                    'event.event_type_scope_id',
                    'event_type.slug as event_type_slug',
                    'event.scope as event_scope',
                    'occurrence.id as occurrence_id',
                    'occurrence.starts_at',
                    'result.player_id',
                    'context.player_name_snapshot as player_name',
                    'metric.value',
                    'metric.dimension_key',
                    'definition.key as metric_key',
                    'definition.label_key as label_key',
                    'definition.unit',
                    'definition.aggregation',
                    'definition.higher_is_better',
                ]);

        $samples = [];
        foreach ($scores as $row) {
            $samples[] = [
                'eventTypeScopeId' => (string) $row->event_type_scope_id,
                'eventTypeSlug' => (string) $row->event_type_slug,
                'eventScope' => (string) $row->event_scope,
                'occurrenceId' => (string) $row->occurrence_id,
                'startsAt' => (string) $row->starts_at,
                'playerId' => (string) $row->player_id,
                'playerName' => (string) $row->player_name,
                'metricKey' => 'score',
                'dimensionKey' => null,
                'labelKey' => (string) $row->label_key,
                'unit' => $row->unit === null ? null : (string) $row->unit,
                'aggregation' => 'average',
                'higherIsBetter' => $row->higher_is_better === null ? null : (bool) $row->higher_is_better,
                'value' => (float) $row->value,
            ];
        }

        foreach ($metrics as $row) {
            $samples[] = [
                'eventTypeScopeId' => (string) $row->event_type_scope_id,
                'eventTypeSlug' => (string) $row->event_type_slug,
                'eventScope' => (string) $row->event_scope,
                'occurrenceId' => (string) $row->occurrence_id,
                'startsAt' => (string) $row->starts_at,
                'playerId' => (string) $row->player_id,
                'playerName' => (string) $row->player_name,
                'metricKey' => (string) $row->metric_key,
                'dimensionKey' => $row->dimension_key === null || (string) $row->dimension_key === '' ? null : (string) $row->dimension_key,
                'labelKey' => (string) $row->label_key,
                'unit' => $row->unit === null ? null : (string) $row->unit,
                'aggregation' => (string) $row->aggregation,
                'higherIsBetter' => $row->higher_is_better === null ? null : (bool) $row->higher_is_better,
                'value' => (float) $row->value,
            ];
        }

        usort($samples, static fn (array $left, array $right): int => strcmp((string) $left['startsAt'], (string) $right['startsAt']));

        return array_slice($samples, 0, $limit);
    }

    /**
     * @param  array{event_type_slug?:string|null,metric_key?:string|null,from?:DateTimeInterface|null,until?:DateTimeInterface|null,max_samples?:int|null}  $filters
     */
    private function filters(Builder $query, array $filters): void
    {
        if (isset($filters['event_type_slug']) && trim((string) $filters['event_type_slug']) !== '') {
            $query->where('event_type.slug', trim((string) $filters['event_type_slug']));
        }
        if (($filters['from'] ?? null) instanceof DateTimeInterface) {
            $query->where('occurrence.starts_at', '>=', $filters['from']);
        }
        if (($filters['until'] ?? null) instanceof DateTimeInterface) {
            $query->where('occurrence.starts_at', '<=', $filters['until']);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $samples
     * @return array{series:list<array<string,mixed>>,leaderboards:list<array<string,mixed>>}
     */
    private function summarize(array $samples, bool $includeLeaderboards): array
    {
        $groups = [];
        foreach ($samples as $sample) {
            $key = implode('|', [
                (string) $sample['eventTypeScopeId'],
                (string) $sample['metricKey'],
                (string) ($sample['dimensionKey'] ?? ''),
            ]);
            $groups[$key][] = $sample;
        }

        $series = [];
        $leaderboards = [];
        foreach ($groups as $groupSamples) {
            $first = $groupSamples[0];
            $values = array_map(static fn (array $sample): float => (float) $sample['value'], $groupSamples);
            $latest = $groupSamples[array_key_last($groupSamples)];
            $higherIsBetter = $first['higherIsBetter'];

            $series[] = [
                'eventTypeScopeId' => $first['eventTypeScopeId'],
                'eventTypeSlug' => $first['eventTypeSlug'],
                'eventScope' => $first['eventScope'],
                'metricKey' => $first['metricKey'],
                'dimensionKey' => $first['dimensionKey'],
                'labelKey' => $first['labelKey'],
                'unit' => $first['unit'],
                'aggregation' => $first['aggregation'],
                'higherIsBetter' => $higherIsBetter,
                'samples' => count($values),
                'average' => round(array_sum($values) / count($values), 4),
                'minimum' => min($values),
                'maximum' => max($values),
                'best' => $higherIsBetter === true ? max($values) : ($higherIsBetter === false ? min($values) : null),
                'latest' => [
                    'occurrenceId' => $latest['occurrenceId'],
                    'startsAt' => $latest['startsAt'],
                    'value' => $latest['value'],
                ],
                'points' => array_map(static fn (array $sample): array => [
                    'occurrenceId' => $sample['occurrenceId'],
                    'startsAt' => $sample['startsAt'],
                    'value' => $sample['value'],
                ], $groupSamples),
            ];

            if (! $includeLeaderboards || $higherIsBetter === null) {
                continue;
            }

            $byPlayer = [];
            foreach ($groupSamples as $sample) {
                $byPlayer[(string) $sample['playerId']][] = $sample;
            }

            $entries = [];
            foreach ($byPlayer as $playerSamples) {
                $playerValues = array_map(static fn (array $sample): float => (float) $sample['value'], $playerSamples);
                $playerLatest = $playerSamples[array_key_last($playerSamples)];
                $entries[] = [
                    'playerId' => $playerLatest['playerId'],
                    'playerName' => $playerLatest['playerName'],
                    'samples' => count($playerValues),
                    'value' => $this->aggregate($playerValues, (string) $first['aggregation']),
                    'average' => round(array_sum($playerValues) / count($playerValues), 4),
                    'best' => $higherIsBetter === true ? max($playerValues) : min($playerValues),
                    'latest' => (float) $playerLatest['value'],
                ];
            }

            usort($entries, static function (array $left, array $right) use ($higherIsBetter): int {
                $comparison = ((float) $left['value']) <=> ((float) $right['value']);
                if ($comparison === 0) {
                    return strcmp((string) $left['playerName'], (string) $right['playerName']);
                }

                return $higherIsBetter === true ? -$comparison : $comparison;
            });

            $leaderboards[] = [
                'eventTypeScopeId' => $first['eventTypeScopeId'],
                'eventTypeSlug' => $first['eventTypeSlug'],
                'eventScope' => $first['eventScope'],
                'metricKey' => $first['metricKey'],
                'dimensionKey' => $first['dimensionKey'],
                'labelKey' => $first['labelKey'],
                'unit' => $first['unit'],
                'aggregation' => $first['aggregation'],
                'higherIsBetter' => $higherIsBetter,
                'entries' => array_slice($entries, 0, 100),
            ];
        }

        return ['series' => $series, 'leaderboards' => $leaderboards];
    }

    /** @param list<float> $values */
    private function aggregate(array $values, string $aggregation): float
    {
        return match ($aggregation) {
            'sum' => array_sum($values),
            'max' => max($values),
            'min' => min($values),
            'latest' => $values[array_key_last($values)],
            default => round(array_sum($values) / count($values), 4),
        };
    }
}
