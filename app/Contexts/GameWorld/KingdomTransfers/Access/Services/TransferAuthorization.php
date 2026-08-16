<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Access\Services;

use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;

final class TransferAuthorization
{
    public function allows(Player $actor, Alliance $alliance, TransferPermission $permission): bool
    {
        if ($alliance->status !== AllianceStatus::Active
            || (string) $actor->current_kingdom_id !== (string) $alliance->kingdom_id) {
            return false;
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $actor->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        return $membership instanceof AllianceMembership
            && $this->allowsMembership($membership, $alliance, $permission);
    }

    public function allowsMembership(
        AllianceMembership $membership,
        Alliance $alliance,
        TransferPermission $permission,
    ): bool {
        if ($membership->status !== MembershipStatus::Active
            || (string) $membership->alliance_id !== (string) $alliance->id) {
            return false;
        }

        return match ($permission) {
            // Preserve the established Transfer management contract: R4/R5 may
            // coordinate a transfer cycle. This is workflow policy, not Intelligence.
            TransferPermission::Manage => in_array($membership->rank, [AllianceRank::R4, AllianceRank::R5], true),
        };
    }
}
