<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Communications\Delivery;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Communications/Delivery';

    protected const SOURCES = [
        'app/Contexts/Communications/Delivery',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/communications/reminder-delivery.md';
}
