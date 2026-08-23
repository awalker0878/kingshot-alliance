<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Queries;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Results\Enums\BearHuntBattleReportStatus;
use App\Contexts\Operations\Results\Models\BearHuntBattleReport;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final readonly class BearHuntDebriefResultQuery
{
    public function __construct(private PlayerReferenceQuery $players) {}

    /**
     * Return authoritative Bear Hunt result facts for one Event occurrence.
     *
     * `damage` is the Operations-owned projected Event Player score. The debrief
     * never re-sums OCR/review rows because the projector may include a preserved
     * pre-import baseline and may have recomputed after accepted-report removal.
     *
     * @return array{
     *   available:bool,
     *   acceptedReportCount:int,
     *   totalDamage:?int,
     *   governorCount:int,
     *   governors:list<array{
     *     playerId:string,
     *     playerName:?string,
     *     damage:int,
     *     rank:?int,
     *     acceptedReportCount:int,
     *     recordedAt:?string
     *   }>
     * }
     */
    public function forOccurrence(string $occurrenceId): array
    {
        $results = EventPlayerResult::query()
            ->where('occurrence_id', $occurrenceId)
            ->whereNotNull('score')
            ->orderByRaw('rank IS NULL')
            ->orderBy('rank')
            ->orderByDesc('score')
            ->orderBy('player_id')
            ->get();

        $playerIds = $results
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();
        $playerReferences = $this->players->byIds($playerIds);

        $contributionCounts = [];
        if ($playerIds !== []) {
            foreach (DB::table('bear_hunt_battle_report_entries as entry')
                ->join('bear_hunt_battle_reports as report', 'report.id', '=', 'entry.report_id')
                ->where('report.occurrence_id', $occurrenceId)
                ->where('report.status', BearHuntBattleReportStatus::Accepted->value)
                ->whereIn('entry.player_id', $playerIds)
                ->groupBy('entry.player_id')
                ->get([
                    'entry.player_id',
                    DB::raw('COUNT(*) AS accepted_report_count'),
                ]) as $row) {
                $contributionCounts[(string) $row->player_id] = (int) $row->accepted_report_count;
            }
        }

        $governors = [];
        $totalDamage = 0;
        foreach ($results as $result) {
            $playerId = (string) $result->player_id;
            $damage = (int) $result->score;
            $reference = $playerReferences[$playerId] ?? null;
            $totalDamage += $damage;

            $governors[] = [
                'playerId' => $playerId,
                'playerName' => $reference?->currentName,
                'damage' => $damage,
                'rank' => $result->rank === null ? null : (int) $result->rank,
                'acceptedReportCount' => $contributionCounts[$playerId] ?? 0,
                'recordedAt' => $this->iso($result->recorded_at),
            ];
        }

        $available = $governors !== [];

        return [
            'available' => $available,
            'acceptedReportCount' => BearHuntBattleReport::query()
                ->where('occurrence_id', $occurrenceId)
                ->where('status', BearHuntBattleReportStatus::Accepted->value)
                ->count(),
            'totalDamage' => $available ? $totalDamage : null,
            'governorCount' => count($governors),
            'governors' => $governors,
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
