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

    public function description(): string
    {
        return match ($this) {
            self::Administrator => 'Manages Kingdom governance roles and receives the default Kingdom operations authority.',
            self::EventCoordinator => 'Coordinates Kingdom Events and Territory operations without governance-role administration.',
            self::Viewer => 'Receives read-only Kingdom Event and Territory visibility.',
        };
    }
}
