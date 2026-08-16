<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Alliance\Membership;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Alliance/Membership';

    protected const SOURCES = [
        'app/Contexts/Alliance/Membership',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/alliance/membership-and-authority.md';
}
