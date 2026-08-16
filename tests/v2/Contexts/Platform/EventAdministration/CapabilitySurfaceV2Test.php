<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Platform\EventAdministration;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Platform/EventAdministration';

    protected const SOURCES = [
        'app/Contexts/Platform/EventAdministration',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/platform/event-administration.md';
}
