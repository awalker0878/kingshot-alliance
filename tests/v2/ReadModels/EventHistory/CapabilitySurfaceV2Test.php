<?php

declare(strict_types=1);

namespace Tests\v2\ReadModels\EventHistory;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'ReadModels/EventHistory';

    protected const SOURCES = [
        'app/ReadModels/EventHistory',
    ];

    protected const DOCUMENTATION = 'docs/codebase/module-map.md';
}
