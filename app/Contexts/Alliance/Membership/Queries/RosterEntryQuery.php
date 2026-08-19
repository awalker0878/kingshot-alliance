<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Queries;

use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;

final class RosterEntryQuery
{
    public function find(string $allianceId, string $rosterEntryId): ?RosterEntryReference
    {
        $entry = AllianceRosterEntry::query()
            ->where('alliance_id', $allianceId)
            ->find($rosterEntryId);

        return $entry instanceof AllianceRosterEntry ? $this->snapshot($entry) : null;
    }

    public function requireActiveOrTracked(string $allianceId, string $rosterEntryId): RosterEntryReference
    {
        $entry = AllianceRosterEntry::query()
            ->where('alliance_id', $allianceId)
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])
            ->findOrFail($rosterEntryId);

        return $this->snapshot($entry);
    }

    /** @return list<RosterEntryReference> */
    public function all(string $allianceId): array
    {
        return array_values(
            AllianceRosterEntry::query()    
            ->where('alliance_id', $allianceId)    
            ->orderBy('observed_name')    
            ->get()    
            ->map(fn (AllianceRosterEntry $entry): RosterEntryReference => $this->snapshot($entry))    
            ->values()    
            ->all(),
        );
    }

    /** @return list<RosterEntryReference> */
    public function activeOrTracked(string $allianceId): array
    {
        return array_values(
            AllianceRosterEntry::query()    
            ->where('alliance_id', $allianceId)    
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])    
            ->orderBy('observed_name')    
            ->get()    
            ->map(fn (AllianceRosterEntry $entry): RosterEntryReference => $this->snapshot($entry))    
            ->values()    
            ->all(),
        );
    }

    /**
     * @param  list<string>  $rosterEntryIds
     * @return array<string, RosterEntryReference>
     */
    public function byIds(string $allianceId, array $rosterEntryIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (string $id): string => trim($id),
            $rosterEntryIds,
        ), static fn (string $id): bool => $id !== '')));

        if ($ids === []) {
            return [];
        }

        $references = [];
        foreach (AllianceRosterEntry::query()
            ->where('alliance_id', $allianceId)
            ->whereIn('id', $ids)
            ->get() as $entry) {
            $reference = $this->snapshot($entry);
            $references[$reference->rosterEntryId] = $reference;
        }

        return $references;
    }

    public function hasActiveOrTrackedOutsideKingdom(string $playerId, string $kingdomId, ?string $exceptAllianceId = null): bool
    {
        $query = AllianceRosterEntry::query()
            ->join('alliances', 'alliances.id', '=', 'alliance_roster_entries.alliance_id')
            ->where('alliance_roster_entries.player_id', $playerId)
            ->whereIn('alliance_roster_entries.state', [RosterState::Active->value, RosterState::Tracked->value])
            ->where('alliances.kingdom_id', '!=', $kingdomId);

        if ($exceptAllianceId !== null) {
            $query->where('alliance_roster_entries.alliance_id', '!=', $exceptAllianceId);
        }

        return $query->exists();
    }

    /** @return list<RosterEntryReference> */
    public function forPlayer(string $allianceId, string $playerId, int $limit = 2): array
    {
        return array_values(
            AllianceRosterEntry::query()    
            ->where('alliance_id', $allianceId)    
            ->where('player_id', $playerId)    
            ->orderBy('id')    
            ->limit(max(1, $limit))    
            ->get()    
            ->map(fn (AllianceRosterEntry $entry): RosterEntryReference => $this->snapshot($entry))    
            ->values()    
            ->all(),
        );
    }

    public function hasActiveRosterPresence(string $allianceId, string $playerId): bool
    {
        return AllianceRosterEntry::query()
            ->where('alliance_id', $allianceId)
            ->where('player_id', $playerId)
            ->where('state', RosterState::Active->value)
            ->exists();
    }

    public function lockActiveRosterPresence(string $allianceId, string $playerId): bool
    {
        return AllianceRosterEntry::query()
            ->where('alliance_id', $allianceId)
            ->where('player_id', $playerId)
            ->where('state', RosterState::Active->value)
            ->lockForUpdate()
            ->exists();
    }

    /** @return list<string> */
    public function activePlayerIds(string $allianceId): array
    {
        return array_values(
            AllianceRosterEntry::query()    
            ->where('alliance_id', $allianceId)    
            ->where('state', RosterState::Active->value)    
            ->orderBy('player_id')    
            ->pluck('player_id')    
            ->map(static fn ($id): string => (string) $id)    
            ->unique()    
            ->values()    
            ->all(),
        );
    }

    /** @return list<string> */
    public function activeAllianceIdsForPlayerInKingdom(string $playerId, string $kingdomId): array
    {
        return array_values(
            AllianceRosterEntry::query()    
            ->join('alliances', 'alliances.id', '=', 'alliance_roster_entries.alliance_id')    
            ->where('alliance_roster_entries.player_id', $playerId)    
            ->where('alliance_roster_entries.state', RosterState::Active->value)    
            ->where('alliances.kingdom_id', $kingdomId)    
            ->orderBy('alliance_roster_entries.alliance_id')    
            ->pluck('alliance_roster_entries.alliance_id')    
            ->map(static fn ($id): string => (string) $id)    
            ->unique()    
            ->values()    
            ->all(),
        );
    }

    /** @return list<string> */
    public function lockActiveAllianceIdsForPlayerInKingdom(string $playerId, string $kingdomId): array
    {
        return array_values(
            AllianceRosterEntry::query()    
            ->where('player_id', $playerId)    
            ->where('state', RosterState::Active->value)    
            ->whereHas('alliance', static fn ($query) => $query->where('kingdom_id', $kingdomId))    
            ->orderBy('alliance_id')    
            ->lockForUpdate()    
            ->pluck('alliance_id')    
            ->map(static fn ($id): string => (string) $id)    
            ->unique()    
            ->values()    
            ->all(),
        );
    }

    public function hasActiveOrTrackedOutsideAlliance(string $playerId, string $allianceId): bool
    {
        return AllianceRosterEntry::query()
            ->where('player_id', $playerId)
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])
            ->where('alliance_id', '!=', $allianceId)
            ->exists();
    }

    private function snapshot(AllianceRosterEntry $entry): RosterEntryReference
    {
        return new RosterEntryReference(
            rosterEntryId: (string) $entry->id,
            allianceId: (string) $entry->alliance_id,
            playerId: (string) $entry->player_id,
            observedName: (string) $entry->observed_name,
            stateObservedAtRead: $entry->state,
            gameRole: $entry->game_role === null ? null : (string) $entry->game_role,
            joinedAt: $entry->joined_at?->toDateString(),
            managerNotes: $entry->manager_notes === null ? null : (string) $entry->manager_notes,
            source: (string) $entry->source,
        );
    }
}
