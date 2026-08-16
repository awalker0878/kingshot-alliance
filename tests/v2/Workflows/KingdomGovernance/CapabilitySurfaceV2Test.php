<?php

declare(strict_types=1);

namespace Tests\v2\Workflows\KingdomGovernance;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Workflows/KingdomGovernance';

    protected const SOURCES = [
        'app/Workflows/KingdomGovernance',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/game-world/kingdom-governance.md';
}
