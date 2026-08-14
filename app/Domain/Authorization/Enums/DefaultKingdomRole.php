<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

enum DefaultKingdomRole: string
{
    case Administrator = 'kingdom_admin';
    case EventCoordinator = 'kingdom_event_coordinator';
    case Viewer = 'kingdom_viewer';

    public function name(): string
    {
        return match ($this) {
            self::Administrator => 'Kingdom Admin',
            self::EventCoordinator => 'Kingdom Event Coordinator',
            self::Viewer => 'Kingdom Viewer',
        };
    }

    /** @return list<PermissionKey> */
    public function permissions(): array
    {
        return match ($this) {
            self::Administrator => [
                PermissionKey::EventKingdomView,
                PermissionKey::EventKingdomCreate,
                PermissionKey::EventKingdomManage,
                PermissionKey::KingdomRoleManage,
            ],
            self::EventCoordinator => [
                PermissionKey::EventKingdomView,
                PermissionKey::EventKingdomCreate,
                PermissionKey::EventKingdomManage,
            ],
            self::Viewer => [
                PermissionKey::EventKingdomView,
            ],
        };
    }
}
