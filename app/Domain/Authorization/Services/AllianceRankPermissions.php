<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Memberships\Enums\AllianceRank;

final class AllianceRankPermissions
{
    /** @return list<PermissionKey> */
    public function for(AllianceRank $rank): array
    {
        $member = [
            PermissionKey::AllianceView,
            PermissionKey::EventPlayerView,
            PermissionKey::EventPlayerCreate,
            PermissionKey::EventAllianceView,
        ];

        return match ($rank) {
            AllianceRank::R1,
            AllianceRank::R2,
            AllianceRank::R3 => $member,
            AllianceRank::R4 => [
                ...$member,
                PermissionKey::MembershipManage,
                PermissionKey::InvitationManage,
                PermissionKey::EventPlayerManage,
                PermissionKey::EventAllianceCreate,
                PermissionKey::EventAllianceManage,
                PermissionKey::KingdomManage,
            ],
            AllianceRank::R5 => [
                ...$member,
                PermissionKey::AllianceManage,
                PermissionKey::MembershipManage,
                PermissionKey::RoleManage,
                PermissionKey::InvitationManage,
                PermissionKey::ContentManage,
                PermissionKey::EventPlayerManage,
                PermissionKey::EventAllianceCreate,
                PermissionKey::EventAllianceManage,
                PermissionKey::RecruitmentManage,
                PermissionKey::ContributionManage,
                PermissionKey::KingdomManage,
            ],
        };
    }

    public function allows(AllianceRank $rank, PermissionKey $permission): bool
    {
        return in_array($permission, $this->for($rank), true);
    }
}
