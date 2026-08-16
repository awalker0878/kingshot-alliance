<?php

declare(strict_types=1);

namespace Tests\v2\Workflows\PlayerContext;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Workflows/PlayerContext';

    protected const SOURCES = [
        'app/Workflows/PlayerContext',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/game-world/player-context.md';
}
