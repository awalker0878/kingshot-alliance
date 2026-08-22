<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Queries;

use App\Contexts\Operations\Results\Enums\BearHuntBattleReportStatus;
use App\Contexts\Operations\Results\Models\BearHuntBattleReport;
use App\Contexts\Operations\Results\Models\EventPlayerResult;

final class BearHuntResultSnapshotQuery
{
    /** @param list<string> $playerIds @return array<string,array{score:int,rank:?int}> */
    public function players(string $occurrenceId, array $playerIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('trim', $playerIds), static fn (string $id): bool => $id !== '')));
        if ($ids === []) {
            return [];
        }
        $rows = [];
        foreach (EventPlayerResult::query()->where('occurrence_id', $occurrenceId)->whereIn('player_id', $ids)->get() as $result) {
            $rows[(string) $result->player_id] = ['score' => (int) ($result->score ?? 0), 'rank' => $result->rank === null ? null : (int) $result->rank];
        }

        return $rows;
    }

    public function acceptedReportCount(string $occurrenceId): int
    {
        return BearHuntBattleReport::query()->where('occurrence_id', $occurrenceId)->where('status', BearHuntBattleReportStatus::Accepted->value)->count();
    }
}
