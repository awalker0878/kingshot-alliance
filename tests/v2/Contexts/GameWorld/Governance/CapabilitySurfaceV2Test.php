<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\GameWorld\Governance;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/GameWorld/Governance';

    protected const SOURCES = [
        'app/Contexts/GameWorld/Governance',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/game-world/kingdom-governance.md';
}
