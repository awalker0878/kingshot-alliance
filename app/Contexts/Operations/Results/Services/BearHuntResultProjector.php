<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Services;

use App\Contexts\Operations\Results\Enums\BearHuntBattleReportStatus;
use App\Contexts\Operations\Results\Models\BearHuntBattleReportEntry;
use App\Contexts\Operations\Results\Models\BearHuntResultBaseline;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use Illuminate\Support\Facades\DB;
use LogicException;

final class BearHuntResultProjector
{
    /** @return list<array{playerId:string,score:int,rank:?int}> */
    public function recompute(string $occurrenceId, string $actorPlayerId): array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Bear Hunt result projection must run inside the owner transaction.');
        }

        $baselines = BearHuntResultBaseline::query()
            ->where('occurrence_id', $occurrenceId)
            ->orderBy('player_id')
            ->lockForUpdate()
            ->get();

        $rows = [];
        foreach ($baselines as $baseline) {
            $damage = (int) BearHuntBattleReportEntry::query()
                ->join('bear_hunt_battle_reports', 'bear_hunt_battle_reports.id', '=', 'bear_hunt_battle_report_entries.report_id')
                ->where('bear_hunt_battle_reports.occurrence_id', $occurrenceId)
                ->where('bear_hunt_battle_reports.status', BearHuntBattleReportStatus::Accepted->value)
                ->where('bear_hunt_battle_report_entries.player_id', $baseline->player_id)
                ->sum('bear_hunt_battle_report_entries.damage_points');
            $acceptedCount = BearHuntBattleReportEntry::query()
                ->join('bear_hunt_battle_reports', 'bear_hunt_battle_reports.id', '=', 'bear_hunt_battle_report_entries.report_id')
                ->where('bear_hunt_battle_reports.occurrence_id', $occurrenceId)
                ->where('bear_hunt_battle_reports.status', BearHuntBattleReportStatus::Accepted->value)
                ->where('bear_hunt_battle_report_entries.player_id', $baseline->player_id)
                ->count();
            $baselineScore = $baseline->baseline_score === null ? 0 : (int) $baseline->baseline_score;
            $rows[] = [
                'playerId' => (string) $baseline->player_id,
                'score' => $baselineScore + $damage,
                'hasAccepted' => $acceptedCount > 0,
                'baselineRank' => $baseline->baseline_rank === null ? null : (int) $baseline->baseline_rank,
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

        $projected = [];
        foreach ($rows as $row) {
            $baseline = $baselines->firstWhere('player_id', $row['playerId']);
            if (! $baseline instanceof BearHuntResultBaseline) {
                continue;
            }
            $result = EventPlayerResult::query()
                ->where('occurrence_id', $occurrenceId)
                ->where('player_id', $row['playerId'])
                ->lockForUpdate()
                ->first();

            if (! $row['hasAccepted']) {
                if (! $result instanceof EventPlayerResult && $baseline->source_event_player_result_id === null) {
                    continue;
                }
                if (! $result instanceof EventPlayerResult) {
                    $result = new EventPlayerResult(['occurrence_id' => $occurrenceId, 'player_id' => $row['playerId']]);
                }
                $result->forceFill([
                    'score' => $baseline->baseline_score,
                    'rank' => $baseline->baseline_rank,
                    'recorded_by_player_id' => $actorPlayerId,
                    'recorded_at' => now(),
                ])->save();
                $projected[] = [
                    'playerId' => $row['playerId'],
                    'score' => (int) ($baseline->baseline_score ?? 0),
                    'rank' => $baseline->baseline_rank === null ? null : (int) $baseline->baseline_rank,
                ];

                continue;
            }

            if (! $result instanceof EventPlayerResult) {
                $result = new EventPlayerResult(['occurrence_id' => $occurrenceId, 'player_id' => $row['playerId']]);
            }
            $rank = $ranks[$row['playerId']] ?? null;
            $result->forceFill([
                'score' => $row['score'],
                'rank' => $rank,
                'recorded_by_player_id' => $actorPlayerId,
                'recorded_at' => now(),
            ])->save();
            $projected[] = ['playerId' => $row['playerId'], 'score' => $row['score'], 'rank' => $rank];
        }

        usort($projected, static fn (array $a, array $b): int => ($a['rank'] ?? PHP_INT_MAX) <=> ($b['rank'] ?? PHP_INT_MAX) ?: strcmp($a['playerId'], $b['playerId']));

        return $projected;
    }
}
