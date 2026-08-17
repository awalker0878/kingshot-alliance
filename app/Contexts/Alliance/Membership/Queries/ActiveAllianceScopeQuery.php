<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Queries;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;

final class ActiveAllianceScopeQuery
{
    public function findForPlayer(string $playerId, string $kingdomId): ?AllianceScopeReference
    {
        $membership = AllianceMembership::query()
            ->select('alliance_memberships.id', 'alliance_memberships.player_id', 'alliance_memberships.alliance_id', 'alliances.kingdom_id')
            ->join('alliances', 'alliances.id', '=', 'alliance_memberships.alliance_id')
            ->where('alliance_memberships.player_id', $playerId)
            ->where('alliance_memberships.status', MembershipStatus::Active->value)
            ->where('alliances.status', AllianceStatus::Active->value)
            ->where('alliances.kingdom_id', $kingdomId)
            ->first();

        if (! $membership instanceof AllianceMembership) {
            return null;
        }

        return new AllianceScopeReference(
            playerId: (string) $membership->player_id,
            kingdomId: (string) $membership->getAttribute('kingdom_id'),
            allianceId: (string) $membership->alliance_id,
            membershipId: (string) $membership->id,
        );
    }
}
