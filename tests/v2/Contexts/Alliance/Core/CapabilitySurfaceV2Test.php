<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Alliance\Core;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Alliance/Core';

    protected const SOURCES = [
        'app/Contexts/Alliance/Core',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/alliance/lifecycle-and-settings.md';
}
