<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Operations\KingPerks;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Operations/KingPerks';

    protected const SOURCES = [
        'app/Contexts/Operations/KingPerks',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/operations/king-perks.md';
}
