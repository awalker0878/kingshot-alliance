<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Alliance\Access;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Alliance/Access';

    protected const SOURCES = [
        'app/Contexts/Alliance/Access',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/alliance/membership-and-authority.md';
}
