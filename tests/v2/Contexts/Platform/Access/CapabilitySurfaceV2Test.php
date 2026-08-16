<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Platform\Access;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Platform/Access';

    protected const SOURCES = [
        'app/Contexts/Platform/Access',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/platform/administration-and-lifecycle.md';
}
