<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\EventAnalysis\Queries;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class EventTrendQuery
{
    private const MAX_OCCURRENCES = 2000;

    private const MAX_LEADERBOARD_ROWS = 100;

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
        $rows = DB::table('event_player_results as result')
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

        return array_values($rows);
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
        $rows = DB::table('event_player_result_metrics as metric')
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

        return array_values($rows);
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
        $rows = DB::table('event_results as result')
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

        return array_values($rows);
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
        $rows = DB::table('event_result_metrics as metric')
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

        return array_values($rows);
    }

    /**
     * Rank Players only within one exact Event Type + organization scope.
     * Historical Player names are display evidence; durable player_id is identity.
     *
     * @return list<array{player_id:string,player_name:string,event_count:int,total_score:int,average_score:float,best_score:int}>
     */
    public function organizationPlayerScoreLeaderboard(
        EventScope $scope,
        string $targetId,
        string $eventTypeSlug,
        int $limit = 20,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $until = null,
    ): array {
        $targetColumn = $this->organizationTargetColumn($scope);
        $higherIsBetter = $this->scoreHigherIsBetter($scope, $eventTypeSlug);
        [$from, $until] = $this->window($from, $until);
        $limit = max(1, min(self::MAX_LEADERBOARD_ROWS, $limit));

        $query = DB::table('event_player_results as result')
            ->join('event_player_contexts as context', function ($join): void {
                $join->on('context.occurrence_id', '=', 'result.occurrence_id')
                    ->on('context.player_id', '=', 'result.player_id');
            })
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('event.scope', $scope->value)
            ->where($targetColumn, $targetId)
            ->where('event_type.slug', $eventTypeSlug)
            ->whereNotNull('result.score')
            ->whereBetween('occurrence.starts_at', [$from, $until])
            ->groupBy('result.player_id')
            ->selectRaw('result.player_id')
            ->selectRaw('MAX(context.player_name_snapshot) AS player_name')
            ->selectRaw('COUNT(*) AS event_count')
            ->selectRaw('SUM(result.score) AS total_score')
            ->selectRaw('AVG(result.score) AS average_score')
            ->selectRaw('MAX(result.score) AS max_score')
            ->selectRaw('MIN(result.score) AS min_score');

        if ($higherIsBetter) {
            $query->orderByRaw('SUM(result.score) DESC');
        } else {
            $query->orderByRaw('AVG(result.score) ASC');
        }

        $rows = $query
            ->orderBy('result.player_id')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): array => [
                'player_id' => (string) $row->player_id,
                'player_name' => (string) $row->player_name,
                'event_count' => (int) $row->event_count,
                'total_score' => (int) $row->total_score,
                'average_score' => round((float) $row->average_score, 2),
                'best_score' => $higherIsBetter ? (int) $row->max_score : (int) $row->min_score,
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * Kingdom contribution by represented Alliance is comparable only inside one
     * exact Kingdom Event Type. Historical alliance_id remains the grouping key.
     *
     * @return list<array{alliance_id:string,alliance_name:string,event_count:int,total_score:int,average_score:float,best_score:int}>
     */
    public function kingdomAllianceScoreLeaderboard(
        string $kingdomId,
        string $eventTypeSlug,
        int $limit = 20,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $until = null,
    ): array {
        $scope = EventScope::Kingdom;
        $higherIsBetter = $this->scoreHigherIsBetter($scope, $eventTypeSlug);
        [$from, $until] = $this->window($from, $until);
        $limit = max(1, min(self::MAX_LEADERBOARD_ROWS, $limit));

        $query = DB::table('event_alliance_results as result')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'result.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->join('event_types as event_type', 'event_type.id', '=', 'event.event_type_id')
            ->where('event.scope', $scope->value)
            ->where('event.kingdom_id', $kingdomId)
            ->where('event_type.slug', $eventTypeSlug)
            ->whereNotNull('result.score')
            ->whereBetween('occurrence.starts_at', [$from, $until])
            ->groupBy('result.alliance_id')
            ->selectRaw('result.alliance_id')
            ->selectRaw('MAX(result.alliance_name_snapshot) AS alliance_name')
            ->selectRaw('COUNT(*) AS event_count')
            ->selectRaw('SUM(result.score) AS total_score')
            ->selectRaw('AVG(result.score) AS average_score')
            ->selectRaw('MAX(result.score) AS max_score')
            ->selectRaw('MIN(result.score) AS min_score');

        if ($higherIsBetter) {
            $query->orderByRaw('SUM(result.score) DESC');
        } else {
            $query->orderByRaw('AVG(result.score) ASC');
        }

        $rows = $query
            ->orderBy('result.alliance_id')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): array => [
                'alliance_id' => (string) $row->alliance_id,
                'alliance_name' => (string) $row->alliance_name,
                'event_count' => (int) $row->event_count,
                'total_score' => (int) $row->total_score,
                'average_score' => round((float) $row->average_score, 2),
                'best_score' => $higherIsBetter ? (int) $row->max_score : (int) $row->min_score,
            ])
            ->all();

        return array_values($rows);
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

    private function scoreHigherIsBetter(EventScope $scope, string $eventTypeSlug): bool
    {
        $value = DB::table('event_type_scopes as type_scope')
            ->join('event_types as event_type', 'event_type.id', '=', 'type_scope.event_type_id')
            ->where('type_scope.scope', $scope->value)
            ->where('event_type.slug', $eventTypeSlug)
            ->value('type_scope.result_score_higher_is_better');

        if ($value === null) {
            throw ValidationException::withMessages([
                'event_type' => 'The Event Type does not support the requested scope.',
            ]);
        }

        return (bool) $value;
    }
}
