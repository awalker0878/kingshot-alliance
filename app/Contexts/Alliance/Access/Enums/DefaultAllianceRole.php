<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Enums;

use App\Shared\Access\Contracts\Permission;
use App\Shared\Access\ValueObjects\NamedPermission;

enum DefaultAllianceRole: string
{
    case Recruiter = 'recruiter';
    case EventCoordinator = 'event_coordinator';
    case ContentManager = 'content_manager';

    public function name(): string
    {
        return match ($this) {
            self::Recruiter => 'Recruiter',
            self::EventCoordinator => 'Event Coordinator',
            self::ContentManager => 'Content Manager',
        };
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Recruiter => [
                AlliancePermission::InvitationManage,
                AlliancePermission::RecruitmentManage,
            ],
            self::EventCoordinator => [
                NamedPermission::from('events.alliance.create'),
                NamedPermission::from('events.alliance.manage'),
            ],
            self::ContentManager => [AlliancePermission::ContentManage],
        };
    }
}
