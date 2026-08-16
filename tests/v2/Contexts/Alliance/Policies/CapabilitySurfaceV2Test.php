<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Alliance\Policies;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Alliance/Policies';

    protected const SOURCES = [
        'app/Contexts/Alliance/Policies',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/alliance/policies.md';
}
