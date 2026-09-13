<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Services;

use App\Contexts\Operations\Results\Enums\BearHuntBattleReportStatus;
use App\Contexts\Operations\Results\Models\BearHuntResultBaseline;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class BearHuntResultProjector
{
    // An atomic application work budget, not an asserted game population limit.
    public const int MAX_GOVERNORS = 1000;

    public function recompute(string $occurrenceId, string $actorPlayerId): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Bear Hunt result projection must run inside the owner transaction.');
        }

        $baselines = BearHuntResultBaseline::query()
            ->where('occurrence_id', $occurrenceId)
            ->orderBy('player_id')
            ->limit(self::MAX_GOVERNORS + 1)
            ->lockForUpdate()
            ->get();
        if ($baselines->count() > self::MAX_GOVERNORS) {
            throw ValidationException::withMessages(['entries' => 'A Bear Hunt occurrence supports at most 1000 distinct reviewed Governors. No report or result changes were saved.']);
        }
        if ($baselines->isEmpty()) {
            return;
        }

        $playerIds = $baselines->pluck('player_id')->all();
        $totals = DB::table('bear_hunt_battle_report_entries as entry')
            ->join('bear_hunt_battle_reports as report', 'report.id', '=', 'entry.report_id')
            ->where('report.occurrence_id', $occurrenceId)
            ->where('report.status', BearHuntBattleReportStatus::Accepted->value)
            ->whereIn('entry.player_id', $playerIds)
            ->groupBy('entry.player_id')
            ->selectRaw('entry.player_id, SUM(entry.damage_points) AS damage, COUNT(*) AS accepted_count')
            ->get()->keyBy('player_id');
        $existing = EventPlayerResult::query()->where('occurrence_id', $occurrenceId)
            ->whereIn('player_id', $playerIds)->orderBy('player_id')->lockForUpdate()->get()->keyBy('player_id');

        $rows = [];
        foreach ($baselines as $baseline) {
            $playerId = (string) $baseline->player_id;
            $total = $totals->get($playerId);
            $damage = filter_var($total->damage ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $baselineScore = (int) ($baseline->baseline_score ?? 0);
            if ($damage === false || $baselineScore < 0 || $damage > PHP_INT_MAX - $baselineScore) {
                throw ValidationException::withMessages(['entries' => 'The combined Bear Hunt score exceeds the supported integer range. No report or result changes were saved.']);
            }
            $rows[$playerId] = [
                'playerId' => $playerId,
                'score' => $baselineScore + $damage,
                'hasAccepted' => (int) ($total->accepted_count ?? 0) > 0,
            ];
        }

        $rankable = array_values(array_filter($rows, static fn (array $row): bool => $row['hasAccepted'] || $row['score'] > 0));
        usort($rankable, static fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: strcmp($a['playerId'], $b['playerId']));
        $ranks = [];
        $lastScore = null;
        $lastRank = null;
        foreach ($rankable as $index => $row) {
            $rank = $lastScore !== null && $lastScore === $row['score'] ? $lastRank : $index + 1;
            $ranks[$row['playerId']] = $rank;
            $lastScore = $row['score'];
            $lastRank = $rank;
        }

        $writes = [];
        $recordedAt = now();
        foreach ($baselines as $baseline) {
            $playerId = (string) $baseline->player_id;
            $row = $rows[$playerId];
            if (! $row['hasAccepted'] && ! $existing->has($playerId) && $baseline->source_event_player_result_id === null) {
                continue;
            }
            $writes[] = [
                'occurrence_id' => $occurrenceId,
                'player_id' => $playerId,
                'score' => $row['hasAccepted'] ? $row['score'] : $baseline->baseline_score,
                'rank' => $row['hasAccepted'] ? ($ranks[$playerId] ?? null) : $baseline->baseline_rank,
                'recorded_by_player_id' => $actorPlayerId,
                'recorded_at' => $recordedAt,
            ];
        }
        foreach (array_chunk($writes, 100) as $batch) {
            EventPlayerResult::query()->upsert($batch, ['occurrence_id', 'player_id'], ['score', 'rank', 'recorded_by_player_id', 'recorded_at']);
        }
    }
}
