<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Intelligence\Contributions;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Intelligence/Contributions';

    protected const SOURCES = [
        'app/Contexts/Intelligence/Contributions',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/intelligence/roster-and-contributions.md';
}
