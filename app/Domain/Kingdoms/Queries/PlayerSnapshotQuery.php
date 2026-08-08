<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\PlayerSnapshot;
use Illuminate\Database\Eloquent\Collection;

final class PlayerSnapshotQuery
{
    public const STALE_AFTER_DAYS = 30;

    /**
     * @param  iterable<int, AllianceRosterEntry>  $entries
     * @return array<string, PlayerSnapshot>
     */
    public function latestForEntries(Alliance $alliance, iterable $entries): array
    {
        $entryIds = [];

        foreach ($entries as $entry) {
            $entryIds[] = (string) $entry->id;
        }

        if ($entryIds === []) {
            return [];
        }

        $latest = PlayerSnapshot::query()
            ->where('alliance_id', $alliance->id)
            ->whereIn('roster_entry_id', $entryIds)
            ->whereRaw(
                'player_snapshots.id = (select latest.id from player_snapshots as latest '
                .'where latest.alliance_id = player_snapshots.alliance_id '
                .'and latest.roster_entry_id = player_snapshots.roster_entry_id '
                .'order by latest.captured_at desc, latest.id desc limit 1)'
            )
            ->with('actor:id,name')
            ->get();

        $byEntry = [];

        foreach ($latest as $snapshot) {
            $byEntry[(string) $snapshot->roster_entry_id] = $snapshot;
        }

        return $byEntry;
    }

    public function latestForEntry(Alliance $alliance, AllianceRosterEntry $entry): ?PlayerSnapshot
    {
        return PlayerSnapshot::query()
            ->where('alliance_id', $alliance->id)
            ->where('roster_entry_id', $entry->id)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->with('actor:id,name')
            ->first();
    }

    /** @return Collection<int, PlayerSnapshot> */
    public function historyForEntry(
        Alliance $alliance,
        AllianceRosterEntry $entry,
        int $limit = 250,
    ): Collection {
        return PlayerSnapshot::query()
            ->where('alliance_id', $alliance->id)
            ->where('roster_entry_id', $entry->id)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->with('actor:id,name')
            ->limit($limit)
            ->get();
    }
}
