<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Platform\Integrations;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Platform/Integrations';

    protected const SOURCES = [
        'app/Contexts/Platform/Integrations',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/platform/integrations.md';
}
