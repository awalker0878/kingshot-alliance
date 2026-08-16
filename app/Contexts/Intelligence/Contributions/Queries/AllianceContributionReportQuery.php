<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Queries;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class AllianceContributionReportQuery
{
    public function __construct(private ContributionReportingQuery $contributions) {}

    /** @return list<array<string,scalar|null>> */
    public function rows(Alliance $alliance): array
    {
        $rows = [];

        foreach ($this->contributions->reportRows($alliance) as $record) {
            $rows[] = $this->mergeRow($alliance, [
                'record_kind' => 'contribution',
                'record_id' => (string) $record['record_id'],
                'player_id' => (string) $record['player_id'],
                'player' => (string) $record['player'],
                'historical_alliance_id' => (string) $alliance->id,
                'historical_alliance_name' => (string) $alliance->name,
                'category' => (string) $record['category'],
                'unit' => (string) $record['unit'],
                'value' => (float) $record['value'],
                'period_start' => (string) $record['period_start'],
                'period_end' => (string) $record['period_end'],
                'status' => (string) $record['status'],
                'source' => (string) $record['source'],
                'data_class' => (string) $record['data_class'],
                'evidence' => $record['evidence'],
                'calculation_key' => $record['calculation_key'],
                'calculation_version' => $record['calculation_version'],
                'correction_of_record_id' => $record['correction_of_record_id'],
                'recorded_at' => (string) $record['recorded_at'],
                'approved_at' => $record['approved_at'],
                'reversed_at' => $record['reversed_at'],
                'reversal_reason' => $record['reversal_reason'],
                'correction_reason' => $record['correction_reason'],
            ]);
        }

        array_push(
            $rows,
            ...$this->eventPlayerRows($alliance),
            ...$this->eventAllianceRows($alliance),
            ...$this->eventTargetRows($alliance),
        );

        usort($rows, static function (array $left, array $right): int {
            $date = strcmp((string) $left['recorded_at'], (string) $right['recorded_at']);
            if ($date !== 0) {
                return $date;
            }

            return strcmp((string) $left['record_id'], (string) $right['record_id']);
        });

        return $rows;
    }

    /** @return list<array<string,scalar|null>> */
    private function eventPlayerRows(Alliance $alliance): array
    {
        $summary = DB::table('event_player_results as result')
            ->join('event_player_contexts as context', function (JoinClause $join): void {
                $join->on('context.occurrence_id', '=', 'result.occurrence_id')
                    ->on('context.player_id', '=', 'result.player_id');
            })
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('context.represented_alliance_id', $alliance->id)
            ->get([
                'result.id as record_id',
                'result.player_id',
                'result.outcome',
                'result.score',
                'result.rank',
                'result.recorded_at',
                'context.player_name_snapshot as player',
                'context.represented_alliance_id as historical_alliance_id',
                'context.represented_alliance_name_snapshot as historical_alliance_name',
                'context.kingdom_id_at_event as historical_kingdom_id',
                'event.id as event_id',
                'event.scope as event_scope',
                'event_type.slug as event_type',
                'occurrence.id as occurrence_id',
                'occurrence.starts_at as event_started_at',
            ]);

        $metrics = DB::table('event_player_result_metrics as metric')
            ->join('event_player_results as result', 'result.id', '=', 'metric.event_player_result_id')
            ->join('event_metric_definitions as definition', 'definition.id', '=', 'metric.metric_definition_id')
            ->join('event_player_contexts as context', function (JoinClause $join): void {
                $join->on('context.occurrence_id', '=', 'result.occurrence_id')
                    ->on('context.player_id', '=', 'result.player_id');
            })
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('context.represented_alliance_id', $alliance->id)
            ->get([
                'metric.id as record_id',
                'result.player_id',
                'result.outcome',
                'result.score',
                'result.rank',
                'metric.recorded_at',
                'metric.source',
                'metric.dimension_key',
                'metric.value as metric_value',
                'definition.key as metric_key',
                'definition.label_key as metric_label',
                'definition.unit as metric_unit',
                'context.player_name_snapshot as player',
                'context.represented_alliance_id as historical_alliance_id',
                'context.represented_alliance_name_snapshot as historical_alliance_name',
                'context.kingdom_id_at_event as historical_kingdom_id',
                'event.id as event_id',
                'event.scope as event_scope',
                'event_type.slug as event_type',
                'occurrence.id as occurrence_id',
                'occurrence.starts_at as event_started_at',
            ]);

        return [
            ...$summary->map(fn (object $row): array => $this->eventRow($alliance, (array) $row, 'event_player_result'))->all(),
            ...$metrics->map(fn (object $row): array => $this->eventRow($alliance, (array) $row, 'event_player_metric'))->all(),
        ];
    }

    /** @return list<array<string,scalar|null>> */
    private function eventAllianceRows(Alliance $alliance): array
    {
        $summary = DB::table('event_alliance_results as result')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('result.alliance_id', $alliance->id)
            ->get([
                'result.id as record_id',
                'result.outcome',
                'result.score',
                'result.rank',
                'result.recorded_at',
                'result.alliance_id as historical_alliance_id',
                'result.alliance_name_snapshot as historical_alliance_name',
                'event.kingdom_id as historical_kingdom_id',
                'event.id as event_id',
                'event.scope as event_scope',
                'event_type.slug as event_type',
                'occurrence.id as occurrence_id',
                'occurrence.starts_at as event_started_at',
            ]);

        $metrics = DB::table('event_alliance_result_metrics as metric')
            ->join('event_alliance_results as result', 'result.id', '=', 'metric.event_alliance_result_id')
            ->join('event_metric_definitions as definition', 'definition.id', '=', 'metric.metric_definition_id')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('result.alliance_id', $alliance->id)
            ->get([
                'metric.id as record_id',
                'result.outcome',
                'result.score',
                'result.rank',
                'metric.recorded_at',
                'metric.source',
                'metric.dimension_key',
                'metric.value as metric_value',
                'definition.key as metric_key',
                'definition.label_key as metric_label',
                'definition.unit as metric_unit',
                'result.alliance_id as historical_alliance_id',
                'result.alliance_name_snapshot as historical_alliance_name',
                'event.kingdom_id as historical_kingdom_id',
                'event.id as event_id',
                'event.scope as event_scope',
                'event_type.slug as event_type',
                'occurrence.id as occurrence_id',
                'occurrence.starts_at as event_started_at',
            ]);

        return [
            ...$summary->map(fn (object $row): array => $this->eventRow($alliance, (array) $row, 'event_alliance_result'))->all(),
            ...$metrics->map(fn (object $row): array => $this->eventRow($alliance, (array) $row, 'event_alliance_metric'))->all(),
        ];
    }

    /** @return list<array<string,scalar|null>> */
    private function eventTargetRows(Alliance $alliance): array
    {
        $summary = DB::table('event_results as result')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('event.scope', 'alliance')
            ->where('event.alliance_id', $alliance->id)
            ->get([
                'result.id as record_id',
                'result.outcome',
                'result.score',
                'result.rank',
                'result.recorded_at',
                'event.alliance_id as historical_alliance_id',
                'event.target_display_name as historical_alliance_name',
                'event.id as event_id',
                'event.scope as event_scope',
                'event_type.slug as event_type',
                'occurrence.id as occurrence_id',
                'occurrence.starts_at as event_started_at',
            ]);

        $metrics = DB::table('event_result_metrics as metric')
            ->join('event_results as result', 'result.id', '=', 'metric.event_result_id')
            ->join('event_metric_definitions as definition', 'definition.id', '=', 'metric.metric_definition_id')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('event.scope', 'alliance')
            ->where('event.alliance_id', $alliance->id)
            ->get([
                'metric.id as record_id',
                'result.outcome',
                'result.score',
                'result.rank',
                'metric.recorded_at',
                'metric.source',
                'metric.dimension_key',
                'metric.value as metric_value',
                'definition.key as metric_key',
                'definition.label_key as metric_label',
                'definition.unit as metric_unit',
                'event.alliance_id as historical_alliance_id',
                'event.target_display_name as historical_alliance_name',
                'event.id as event_id',
                'event.scope as event_scope',
                'event_type.slug as event_type',
                'occurrence.id as occurrence_id',
                'occurrence.starts_at as event_started_at',
            ]);

        return [
            ...$summary->map(fn (object $row): array => $this->eventRow($alliance, (array) $row, 'event_result'))->all(),
            ...$metrics->map(fn (object $row): array => $this->eventRow($alliance, (array) $row, 'event_metric'))->all(),
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,scalar|null>
     */
    private function eventRow(Alliance $alliance, array $row, string $kind): array
    {
        return $this->mergeRow($alliance, [
            'record_kind' => $kind,
            'record_id' => (string) $row['record_id'],
            'player_id' => isset($row['player_id']) ? (string) $row['player_id'] : null,
            'player' => isset($row['player']) ? (string) $row['player'] : null,
            'event_id' => (string) $row['event_id'],
            'occurrence_id' => (string) $row['occurrence_id'],
            'event_scope' => (string) $row['event_scope'],
            'event_type' => (string) $row['event_type'],
            'event_started_at' => (string) $row['event_started_at'],
            'historical_alliance_id' => isset($row['historical_alliance_id']) ? (string) $row['historical_alliance_id'] : (string) $alliance->id,
            'historical_alliance_name' => isset($row['historical_alliance_name']) ? (string) $row['historical_alliance_name'] : (string) $alliance->name,
            'historical_kingdom_id' => isset($row['historical_kingdom_id'])
                ? (string) $row['historical_kingdom_id']
                : (string) $alliance->kingdom_id,
            'event_outcome' => isset($row['outcome']) ? (string) $row['outcome'] : null,
            'event_rank' => isset($row['rank']) ? (int) $row['rank'] : null,
            'event_score' => isset($row['score']) ? (int) $row['score'] : null,
            'metric_key' => isset($row['metric_key']) ? (string) $row['metric_key'] : null,
            'metric_label' => isset($row['metric_label']) ? (string) $row['metric_label'] : null,
            'metric_dimension' => isset($row['dimension_key']) && (string) $row['dimension_key'] !== '' ? (string) $row['dimension_key'] : null,
            'metric_unit' => isset($row['metric_unit']) ? (string) $row['metric_unit'] : null,
            'metric_value' => isset($row['metric_value']) ? (float) $row['metric_value'] : null,
            'source' => isset($row['source']) ? (string) $row['source'] : 'event_result',
            'recorded_at' => (string) $row['recorded_at'],
        ]);
    }

    /**
     * @param  array<string,scalar|null>  $values
     * @return array<string,scalar|null>
     */
    private function mergeRow(Alliance $alliance, array $values): array
    {
        return array_replace($this->baseRow($alliance), $values);
    }

    /** @return array<string,scalar|null> */
    private function baseRow(Alliance $alliance): array
    {
        return [
            'record_kind' => null,
            'record_id' => null,
            'player_id' => null,
            'player' => null,
            'event_id' => null,
            'occurrence_id' => null,
            'event_scope' => null,
            'event_type' => null,
            'event_started_at' => null,
            'historical_alliance_id' => (string) $alliance->id,
            'historical_alliance_name' => (string) $alliance->name,
            'historical_kingdom_id' => (string) $alliance->kingdom_id,
            'event_outcome' => null,
            'event_rank' => null,
            'event_score' => null,
            'metric_key' => null,
            'metric_label' => null,
            'metric_dimension' => null,
            'metric_unit' => null,
            'metric_value' => null,
            'category' => null,
            'unit' => null,
            'value' => null,
            'period_start' => null,
            'period_end' => null,
            'status' => null,
            'source' => null,
            'data_class' => null,
            'evidence' => null,
            'calculation_key' => null,
            'calculation_version' => null,
            'correction_of_record_id' => null,
            'recorded_at' => null,
            'approved_at' => null,
            'reversed_at' => null,
            'reversal_reason' => null,
            'correction_reason' => null,
        ];
    }
}
