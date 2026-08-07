<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

enum PermissionKey: string
{
    case AllianceView = 'alliance.view';
    case AllianceManage = 'alliance.manage';
    case MembershipManage = 'membership.manage';
    case RoleManage = 'roles.manage';
    case InvitationManage = 'invitations.manage';
    case ContentManage = 'content.manage';
    case EventManage = 'events.manage';
    case RecruitmentManage = 'recruitment.manage';
    case ContributionManage = 'contributions.manage';

    public function description(): string
    {
        return match ($this) {
            self::AllianceView => 'View alliance member areas.',
            self::AllianceManage => 'Manage alliance settings.',
            self::MembershipManage => 'Manage alliance memberships.',
            self::RoleManage => 'Manage alliance role assignments.',
            self::InvitationManage => 'Create, revoke, and resend invitations.',
            self::ContentManage => 'Manage alliance content.',
            self::EventManage => 'Manage alliance events and rally configuration.',
            self::RecruitmentManage => 'Manage recruitment workflows.',
            self::ContributionManage => 'Manage alliance contribution records, reporting, exports, and report schedules.',
        };
    }
}
