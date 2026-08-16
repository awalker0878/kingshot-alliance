<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\GameWorld\Identity;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/GameWorld/Identity';

    protected const SOURCES = [
        'app/Contexts/GameWorld/Actions',
        'app/Contexts/GameWorld/Enums',
        'app/Contexts/GameWorld/Models',
        'app/Contexts/GameWorld/Services',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/game-world/player-context.md';
}
