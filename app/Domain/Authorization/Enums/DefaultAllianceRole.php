<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

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

    /** @return list<PermissionKey> */
    public function permissions(): array
    {
        return match ($this) {
            self::Recruiter => [
                PermissionKey::InvitationManage,
                PermissionKey::RecruitmentManage,
            ],
            self::EventCoordinator => [
                PermissionKey::EventAllianceCreate,
                PermissionKey::EventAllianceManage,
            ],
            self::ContentManager => [
                PermissionKey::ContentManage,
            ],
        };
    }
}
