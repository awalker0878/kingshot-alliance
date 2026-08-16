<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Operations\EventCore;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Operations/EventCore';

    protected const SOURCES = [
        'app/Contexts/Operations/EventCore',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/operations/event-core.md';
}
