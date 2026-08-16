<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Operations\BattlePlans;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Operations/BattlePlans';

    protected const SOURCES = [
        'app/Contexts/Operations/BattlePlans',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/operations/planning.md';
}
