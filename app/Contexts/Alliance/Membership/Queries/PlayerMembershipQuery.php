<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Queries;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;

final class PlayerMembershipQuery
{
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
}
