<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Queries;

use App\Contexts\Operations\Results\ValueObjects\BearHuntBattleReportReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class BearHuntReportReceiptQuery
{
    public function forReport(string $occurrenceId, string $reportId, bool $replay): BearHuntBattleReportReceipt
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('The Bear Hunt receipt must be captured in the authorized owner transaction.');
        }
        $rows = DB::table('bear_hunt_battle_report_entries as entry')
            ->join('bear_hunt_battle_reports as report', 'report.id', '=', 'entry.report_id')
            ->leftJoin('event_player_results as result', function ($join): void {
                $join->on('result.occurrence_id', '=', 'report.occurrence_id')->on('result.player_id', '=', 'entry.player_id');
            })
            ->where('report.occurrence_id', $occurrenceId)->where('report.id', $reportId)
            ->orderByRaw('result.rank ASC NULLS LAST')->orderBy('entry.player_id')
            ->limit(101)->get(['entry.player_id', 'result.score', 'result.rank']);
        if ($rows->isEmpty() || $rows->count() > 100) {
            throw ValidationException::withMessages(['report' => 'The stored Bear Hunt report is outside the supported 1 to 100 Governor contract.']);
        }

        return new BearHuntBattleReportReceipt($reportId, $rows->count(), $replay, array_values($rows->map(static fn (object $row): array => [
            'playerId' => (string) $row->player_id,
            'score' => (int) ($row->score ?? 0),
            'rank' => $row->rank === null ? null : (int) $row->rank,
        ])->all()));
    }
}
