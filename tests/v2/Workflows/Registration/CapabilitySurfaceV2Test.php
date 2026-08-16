<?php

declare(strict_types=1);

namespace Tests\v2\Workflows\Registration;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Workflows/Registration';

    protected const SOURCES = [
        'app/Workflows/Registration',
    ];

    protected const DOCUMENTATION = 'docs/codebase/module-map.md';
}
