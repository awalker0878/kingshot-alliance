<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\GameWorld\KingdomTransfers;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Workflows/KingdomTransfer';

    protected const SOURCES = [
        'app/Contexts/GameWorld/KingdomTransfers',
    ];

    protected const DOCUMENTATION = 'docs/codebase/module-map.md';
}
