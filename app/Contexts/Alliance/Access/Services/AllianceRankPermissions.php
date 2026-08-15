<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Shared\Access\Contracts\Permission;
use App\Shared\Access\ValueObjects\NamedPermission;

final class AllianceRankPermissions
{
    /** @return list<Permission> */
    public function for(AllianceRank $rank): array
    {
        $member = [
            AlliancePermission::View,
            NamedPermission::from('events.player.view'),
            NamedPermission::from('events.player.create'),
            NamedPermission::from('events.alliance.view'),
        ];

        return match ($rank) {
            AllianceRank::R1,
            AllianceRank::R2,
            AllianceRank::R3 => $member,
            AllianceRank::R4 => [
                ...$member,
                AlliancePermission::MembershipManage,
                AlliancePermission::InvitationManage,
                NamedPermission::from('events.player.manage'),
                NamedPermission::from('events.alliance.create'),
                NamedPermission::from('events.alliance.manage'),
                NamedPermission::from('kingdoms.manage'),
            ],
            AllianceRank::R5 => [
                ...$member,
                AlliancePermission::Manage,
                AlliancePermission::MembershipManage,
                AlliancePermission::RoleManage,
                AlliancePermission::InvitationManage,
                AlliancePermission::ContentManage,
                NamedPermission::from('events.player.manage'),
                NamedPermission::from('events.alliance.create'),
                NamedPermission::from('events.alliance.manage'),
                AlliancePermission::RecruitmentManage,
                NamedPermission::from('contributions.manage'),
                NamedPermission::from('kingdoms.manage'),
            ],
        };
    }

    public function allows(AllianceRank $rank, Permission $permission): bool
    {
        foreach ($this->for($rank) as $granted) {
            if ($granted->key() === $permission->key()) {
                return true;
            }
        }

        return false;
    }
}
