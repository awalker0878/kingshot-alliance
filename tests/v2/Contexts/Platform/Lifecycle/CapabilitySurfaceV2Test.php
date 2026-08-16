<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Platform\Lifecycle;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Platform/Lifecycle';

    protected const SOURCES = [
        'app/Contexts/Platform/Actions',
        'app/Contexts/Platform/Services',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/platform/administration-and-lifecycle.md';
}
