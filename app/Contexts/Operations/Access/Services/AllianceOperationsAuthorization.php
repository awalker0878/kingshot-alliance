<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;

final class AllianceOperationsAuthorization
{
    public function allows(Player $actor, Alliance $alliance, OperationsPermission $permission): bool
    {
        if ($alliance->status !== AllianceStatus::Active
            || (string) $actor->current_kingdom_id !== (string) $alliance->kingdom_id) {
            return false;
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $actor->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('roles:id,alliance_id,key')
            ->first();

        return $membership instanceof AllianceMembership
            && $this->allowsMembership($membership, $alliance, $permission);
    }

    public function allowsMembership(
        AllianceMembership $membership,
        Alliance $alliance,
        OperationsPermission $permission,
    ): bool {
        if ($membership->status !== MembershipStatus::Active
            || (string) $membership->alliance_id !== (string) $alliance->id) {
            return false;
        }

        $isOfficer = in_array($membership->rank, [AllianceRank::R4, AllianceRank::R5], true);
        $isEventCoordinator = $membership->roles->contains(
            static fn ($role): bool => (string) $role->alliance_id === (string) $alliance->id
                && (string) $role->key === DefaultAllianceRole::EventCoordinator->value,
        );

        return match ($permission) {
            OperationsPermission::EventPlayerView,
            OperationsPermission::EventPlayerCreate,
            OperationsPermission::EventAllianceView => true,
            OperationsPermission::EventPlayerManage => $isOfficer,
            OperationsPermission::EventAllianceCreate,
            OperationsPermission::EventAllianceManage => $isOfficer || $isEventCoordinator,
            default => false,
        };
    }
}
