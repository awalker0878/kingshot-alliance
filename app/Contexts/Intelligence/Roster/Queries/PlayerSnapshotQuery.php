<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Queries;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

final class PlayerSnapshotQuery
{
    public const STALE_AFTER_DAYS = 30;

    /**
     * @param  iterable<int, AllianceRosterEntry>  $entries
     * @return array<string, PlayerSnapshot>
     */
    public function latestForEntries(Alliance $alliance, iterable $entries): array
    {
        $entryIds = $this->entryIds($entries);

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
            ->with('actor:id,current_name')
            ->get();

        return $this->byEntry($latest);
    }

    /**
     * For an N-day comparison, select the closest observation at or before now-N days,
     * but not older than now-2N days. This avoids claiming a short interval as an
     * N-day trend and rejects arbitrarily old baselines when history is sparse.
     *
     * @param  iterable<int, AllianceRosterEntry>  $entries
     * @return array<string, PlayerSnapshot>
     */
    public function baselinesForEntries(
        Alliance $alliance,
        iterable $entries,
        int $days,
        Carbon $asOf,
    ): array {
        $entryIds = $this->entryIds($entries);

        if ($entryIds === []) {
            return [];
        }

        $target = $asOf->copy()->subDays($days);
        $oldest = $asOf->copy()->subDays($days * 2);
        $candidates = PlayerSnapshot::query()
            ->where('alliance_id', $alliance->id)
            ->whereIn('roster_entry_id', $entryIds)
            ->where('captured_at', '<=', $target)
            ->where('captured_at', '>=', $oldest)
            ->orderBy('roster_entry_id')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get();
        $byEntry = [];

        foreach ($candidates as $snapshot) {
            $entryId = (string) $snapshot->roster_entry_id;
            $byEntry[$entryId] ??= $snapshot;
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
            ->with('actor:id,current_name')
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
            ->with('actor:id,current_name')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  iterable<int, AllianceRosterEntry>  $entries
     * @return list<string>
     */
    private function entryIds(iterable $entries): array
    {
        $ids = [];

        foreach ($entries as $entry) {
            $ids[] = (string) $entry->id;
        }

        return $ids;
    }

    /**
     * @param  iterable<int, PlayerSnapshot>  $snapshots
     * @return array<string, PlayerSnapshot>
     */
    private function byEntry(iterable $snapshots): array
    {
        $byEntry = [];

        foreach ($snapshots as $snapshot) {
            $byEntry[(string) $snapshot->roster_entry_id] = $snapshot;
        }

        return $byEntry;
    }
}
