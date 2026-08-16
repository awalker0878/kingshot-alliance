<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;

final class AllianceRankPermissions
{
    /** @return list<AlliancePermission> */
    public function for(AllianceRank $rank): array
    {
        $member = [AlliancePermission::View];

        return match ($rank) {
            AllianceRank::R1,
            AllianceRank::R2,
            AllianceRank::R3 => $member,
            AllianceRank::R4 => [
                ...$member,
                AlliancePermission::MembershipManage,
                AlliancePermission::InvitationManage,
            ],
            AllianceRank::R5 => [
                ...$member,
                AlliancePermission::Manage,
                AlliancePermission::MembershipManage,
                AlliancePermission::RoleManage,
                AlliancePermission::InvitationManage,
                AlliancePermission::ContentManage,
                AlliancePermission::RecruitmentManage,
            ],
        };
    }

    public function allows(AllianceRank $rank, AlliancePermission $permission): bool
    {
        return in_array($permission, $this->for($rank), true);
    }
}
