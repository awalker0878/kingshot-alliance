<?php

declare(strict_types=1);

namespace App\Domain\Events\Queries;

use App\Domain\Events\Enums\EventScope;
use App\Domain\Kingdoms\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class EventTrendQuery
{
    private const MAX_OCCURRENCES = 2000;

    public function __construct(private EventPlayerOccurrenceEvidenceQuery $evidence) {}

    /**
     * Universal participation facts are safe to aggregate across Event Types.
     *
     * @return array{completed:int,absent:int,excused:int,unresolved:int,reliability_percent:?float}
     */
    public function playerParticipation(
        Player $player,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $until = null,
    ): array {
        [$from, $until] = $this->window($from, $until);
        $occurrenceIds = DB::table('event_player_contexts as context')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'context.occurrence_id')
            ->where('context.player_id', $player->id)
            ->whereBetween('occurrence.starts_at', [$from, $until])
            ->orderByDesc('occurrence.starts_at')
            ->limit(self::MAX_OCCURRENCES)
            ->pluck('context.occurrence_id')
            ->map(static fn ($id): string => (string) $id);

        $facts = $this->evidence->forPlayer($player, $occurrenceIds);
        $counts = ['completed' => 0, 'absent' => 0, 'excused' => 0, 'unresolved' => 0];
        foreach ($facts as $fact) {
            $outcome = $fact['outcome'];
            if (is_string($outcome) && array_key_exists($outcome, $counts)) {
                $counts[$outcome]++;
            }
        }

        $decided = $counts['completed'] + $counts['absent'];

        return [
            ...$counts,
            'reliability_percent' => $decided === 0
                ? null
                : round(($counts['completed'] / $decided) * 100, 2),
        ];
    }

    /**
     * Scores are comparable only inside one Event Type + scope tuple.
     *
     * @return list<array{occurrence_id:string,starts_at:string,score:?int,rank:?int,outcome:?string}>
     */
    public function playerScoreSeries(
        Player $player,
        string $eventTypeSlug,
        EventScope $scope,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $until = null,
    ): array {
        [$from, $until] = $this->window($from, $until);

        return DB::table('event_player_results as result')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('result.player_id', $player->id)
            ->where('event.scope', $scope->value)
            ->where('event_type.slug', $eventTypeSlug)
            ->whereBetween('occurrence.starts_at', [$from, $until])
            ->orderBy('occurrence.starts_at')
            ->limit(self::MAX_OCCURRENCES)
            ->get(['occurrence.id as occurrence_id', 'occurrence.starts_at', 'result.score', 'result.rank', 'result.outcome'])
            ->map(static fn (object $row): array => [
                'occurrence_id' => (string) $row->occurrence_id,
                'starts_at' => (string) $row->starts_at,
                'score' => $row->score === null ? null : (int) $row->score,
                'rank' => $row->rank === null ? null : (int) $row->rank,
                'outcome' => $row->outcome === null ? null : (string) $row->outcome,
            ])
            ->all();
    }

    /**
     * Metric series require one exact Event Type + scope + metric key.
     *
     * @return list<array{occurrence_id:string,starts_at:string,dimension_key:?string,value:float,unit:?string}>
     */
    public function playerMetricSeries(
        Player $player,
        string $eventTypeSlug,
        EventScope $scope,
        string $metricKey,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $until = null,
    ): array {
        [$from, $until] = $this->window($from, $until);

        return DB::table('event_player_result_metrics as metric')
            ->join('event_player_results as result', 'result.id', '=', 'metric.event_player_result_id')
            ->join('event_metric_definitions as definition', 'definition.id', '=', 'metric.metric_definition_id')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('result.player_id', $player->id)
            ->where('event.scope', $scope->value)
            ->where('event_type.slug', $eventTypeSlug)
            ->where('definition.key', $metricKey)
            ->whereBetween('occurrence.starts_at', [$from, $until])
            ->orderBy('occurrence.starts_at')
            ->orderBy('metric.dimension_key')
            ->limit(self::MAX_OCCURRENCES)
            ->get([
                'occurrence.id as occurrence_id',
                'occurrence.starts_at',
                'metric.dimension_key',
                'metric.value',
                'definition.unit',
            ])
            ->map(static fn (object $row): array => [
                'occurrence_id' => (string) $row->occurrence_id,
                'starts_at' => (string) $row->starts_at,
                'dimension_key' => (string) $row->dimension_key === '' ? null : (string) $row->dimension_key,
                'value' => (float) $row->value,
                'unit' => $row->unit === null ? null : (string) $row->unit,
            ])
            ->all();
    }

    /**
     * Organization score trends require one exact Event Type and immutable organization target.
     *
     * @return list<array{occurrence_id:string,starts_at:string,score:?int,opponent_score:?int,rank:?int,outcome:?string}>
     */
    public function organizationScoreSeries(
        EventScope $scope,
        string $targetId,
        string $eventTypeSlug,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $until = null,
    ): array {
        $targetColumn = $this->organizationTargetColumn($scope);
        [$from, $until] = $this->window($from, $until);

        return DB::table('event_results as result')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('event.scope', $scope->value)
            ->where($targetColumn, $targetId)
            ->where('event_type.slug', $eventTypeSlug)
            ->whereBetween('occurrence.starts_at', [$from, $until])
            ->orderBy('occurrence.starts_at')
            ->limit(self::MAX_OCCURRENCES)
            ->get([
                'occurrence.id as occurrence_id',
                'occurrence.starts_at',
                'result.score',
                'result.opponent_score',
                'result.rank',
                'result.outcome',
            ])
            ->map(static fn (object $row): array => [
                'occurrence_id' => (string) $row->occurrence_id,
                'starts_at' => (string) $row->starts_at,
                'score' => $row->score === null ? null : (int) $row->score,
                'opponent_score' => $row->opponent_score === null ? null : (int) $row->opponent_score,
                'rank' => $row->rank === null ? null : (int) $row->rank,
                'outcome' => $row->outcome === null ? null : (string) $row->outcome,
            ])
            ->all();
    }

    /**
     * Organization metric trends require one exact Event Type + scope + metric key.
     *
     * @return list<array{occurrence_id:string,starts_at:string,dimension_key:?string,value:float,unit:?string}>
     */
    public function organizationMetricSeries(
        EventScope $scope,
        string $targetId,
        string $eventTypeSlug,
        string $metricKey,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $until = null,
    ): array {
        $targetColumn = $this->organizationTargetColumn($scope);
        [$from, $until] = $this->window($from, $until);

        return DB::table('event_result_metrics as metric')
            ->join('event_results as result', 'result.id', '=', 'metric.event_result_id')
            ->join('event_metric_definitions as definition', 'definition.id', '=', 'metric.metric_definition_id')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('event.scope', $scope->value)
            ->where($targetColumn, $targetId)
            ->where('event_type.slug', $eventTypeSlug)
            ->where('definition.key', $metricKey)
            ->whereBetween('occurrence.starts_at', [$from, $until])
            ->orderBy('occurrence.starts_at')
            ->orderBy('metric.dimension_key')
            ->limit(self::MAX_OCCURRENCES)
            ->get([
                'occurrence.id as occurrence_id',
                'occurrence.starts_at',
                'metric.dimension_key',
                'metric.value',
                'definition.unit',
            ])
            ->map(static fn (object $row): array => [
                'occurrence_id' => (string) $row->occurrence_id,
                'starts_at' => (string) $row->starts_at,
                'dimension_key' => (string) $row->dimension_key === '' ? null : (string) $row->dimension_key,
                'value' => (float) $row->value,
                'unit' => $row->unit === null ? null : (string) $row->unit,
            ])
            ->all();
    }

    /** @return array{CarbonImmutable,CarbonImmutable} */
    private function window(?CarbonImmutable $from, ?CarbonImmutable $until): array
    {
        $until ??= CarbonImmutable::now('UTC');
        $from ??= $until->subDays(180);
        if ($from->greaterThan($until)) {
            throw ValidationException::withMessages(['from' => 'Trend start must not be after trend end.']);
        }

        return [$from->utc(), $until->utc()];
    }

    private function organizationTargetColumn(EventScope $scope): string
    {
        return match ($scope) {
            EventScope::Alliance => 'event.alliance_id',
            EventScope::Kingdom => 'event.kingdom_id',
            EventScope::Player => throw ValidationException::withMessages([
                'scope' => 'Organization trend scope must be Alliance or Kingdom.',
            ]),
        };
    }
}
