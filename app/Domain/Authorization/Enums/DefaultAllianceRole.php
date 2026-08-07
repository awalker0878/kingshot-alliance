<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

use App\Domain\Events\Models\Event;

enum DefaultAllianceRole: string
{
    case Owner = 'owner';
    case Leader = 'leader';
    case Officer = 'officer';
    case Member = 'member';
    case Recruiter = 'recruiter';
    case EventCoordinator = 'event_coordinator';
    case ContentManager = 'content_manager';

    public function name(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Leader => 'Leader',
            self::Officer => 'Officer',
            self::Member => 'Member',
            self::Recruiter => 'Recruiter',
            self::EventCoordinator => 'Event Coordinator',
            self::ContentManager => 'Content Manager',
        };
    }

    /** @return list<PermissionKey> */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => PermissionKey::cases(),
            self::Leader => [
                PermissionKey::AllianceView,
                PermissionKey::AllianceManage,
                PermissionKey::MembershipManage,
                PermissionKey::InvitationManage,
                PermissionKey::ContentManage,
                PermissionKey::EventManage,
                PermissionKey::RecruitmentManage,
            ],
            self::Officer => [
                PermissionKey::AllianceView,
                PermissionKey::MembershipManage,
                PermissionKey::InvitationManage,
                PermissionKey::EventManage,
            ],
            self::Member => [PermissionKey::AllianceView],
            self::Recruiter => [
                PermissionKey::AllianceView,
                PermissionKey::InvitationManage,
                PermissionKey::RecruitmentManage,
            ],
            self::EventCoordinator => [
                PermissionKey::AllianceView,
                PermissionKey::EventManage,
            ],
            self::ContentManager => [
                PermissionKey::AllianceView,
                PermissionKey::ContentManage,
            ],
        };
    }
}
