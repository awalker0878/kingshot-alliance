<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Intelligence\Roster;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Intelligence/Roster';

    protected const SOURCES = [
        'app/Contexts/Intelligence/Roster',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/intelligence/roster-and-contributions.md';
}
