<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Enums;

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
}
