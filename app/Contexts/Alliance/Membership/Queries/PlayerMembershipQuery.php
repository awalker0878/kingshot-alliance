<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Queries;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Illuminate\Support\Facades\DB;
use LogicException;

final class PlayerMembershipQuery
{
    public function hasAnyActiveForPlayer(string $playerId): bool
    {
        return AllianceMembership::query()
            ->where('player_id', $playerId)
            ->where('status', MembershipStatus::Active->value)
            ->exists();
    }

    public function lockActiveMember(string $allianceId, string $playerId): bool
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Alliance membership must be locked inside a database transaction.');
        }

        return AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('player_id', $playerId)
            ->where('status', MembershipStatus::Active->value)
            ->lockForUpdate()
            ->exists();
    }

    /** @return list<string> */
    public function lockActivePlayerIds(string $allianceId): array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Alliance membership must be locked inside a database transaction.');
        }

        return AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('status', MembershipStatus::Active->value)
            ->orderBy('player_id')
            ->lockForUpdate()
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    public function isActiveMember(string $allianceId, string $playerId): bool
    {
        return AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('player_id', $playerId)
            ->where('status', MembershipStatus::Active->value)
            ->exists();
    }

    /** @return list<string> */
    public function activePlayerIds(string $allianceId): array
    {
        return AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('status', MembershipStatus::Active->value)
            ->orderBy('player_id')
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    /** @param list<string> $playerIds */
    public function hasActiveR5(array $playerIds): bool
    {
        if ($playerIds === []) {
            return false;
        }

        return AllianceMembership::query()
            ->whereIn('player_id', $playerIds)
            ->where('status', MembershipStatus::Active->value)
            ->where('rank', AllianceRank::R5->value)
            ->exists();
    }

    /** @param list<string> $playerIds */
    public function activeAllianceIds(array $playerIds): array
    {
        if ($playerIds === []) {
            return [];
        }

        return AllianceMembership::query()
            ->whereIn('player_id', $playerIds)
            ->where('status', MembershipStatus::Active->value)
            ->orderBy('alliance_id')
            ->pluck('alliance_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function activeAllianceIdsForPlayerInKingdom(string $playerId, string $kingdomId): array
    {
        return AllianceMembership::query()
            ->where('player_id', $playerId)
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('alliance', static fn ($query) => $query->where('kingdom_id', $kingdomId))
            ->orderBy('alliance_id')
            ->pluck('alliance_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }
}
