<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

use App\Shared\Access\Contracts\Permission;

enum PermissionKey: string implements Permission
{
    case AllianceView = 'alliance.view';
    case AllianceManage = 'alliance.manage';
    case MembershipManage = 'membership.manage';
    case RoleManage = 'roles.manage';
    case InvitationManage = 'invitations.manage';
    case ContentManage = 'content.manage';

    case EventPlayerView = 'events.player.view';
    case EventPlayerCreate = 'events.player.create';
    case EventPlayerManage = 'events.player.manage';
    case EventAllianceView = 'events.alliance.view';
    case EventAllianceCreate = 'events.alliance.create';
    case EventAllianceManage = 'events.alliance.manage';
    case EventKingdomView = 'events.kingdom.view';
    case EventKingdomCreate = 'events.kingdom.create';
    case EventKingdomManage = 'events.kingdom.manage';
    case EventTypeManage = 'events.types.manage';
    case KingdomRoleManage = 'kingdom.roles.manage';

    case RecruitmentManage = 'recruitment.manage';
    case ContributionManage = 'contributions.manage';
    case KingdomManage = 'kingdoms.manage';

    public function key(): string
    {
        return $this->value;
    }

    public function description(): string
    {
        return match ($this) {
            self::AllianceView => 'View alliance member areas.',
            self::AllianceManage => 'Manage alliance settings.',
            self::MembershipManage => 'Manage alliance memberships.',
            self::RoleManage => 'Manage alliance specialist role assignments.',
            self::InvitationManage => 'Create, revoke, and resend invitations.',
            self::ContentManage => 'Manage alliance content.',
            self::EventPlayerView => 'View permitted player-scoped events.',
            self::EventPlayerCreate => 'Create permitted player-scoped events.',
            self::EventPlayerManage => 'Manage permitted player-scoped events.',
            self::EventAllianceView => 'View alliance-scoped events.',
            self::EventAllianceCreate => 'Create alliance-scoped events.',
            self::EventAllianceManage => 'Manage alliance-scoped events and event operations.',
            self::EventKingdomView => 'View permitted kingdom-scoped events.',
            self::EventKingdomCreate => 'Create permitted kingdom-scoped events.',
            self::EventKingdomManage => 'Manage permitted kingdom-scoped events.',
            self::EventTypeManage => 'Manage the event type catalogue and capability configuration.',
            self::KingdomRoleManage => 'Manage roles and role assignments for a specific kingdom.',
            self::RecruitmentManage => 'Manage recruitment workflows.',
            self::ContributionManage => 'Manage alliance contribution records, reporting, exports, and report schedules.',
            self::KingdomManage => 'Manage the alliance game roster, membership links, and roster observations.',
        };
    }
}
