<?php

declare(strict_types=1);

namespace Tests\v2\ReadModels\AllianceDashboard;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'ReadModels/AllianceDashboard';

    protected const SOURCES = [
        'app/ReadModels/AllianceDashboard',
    ];

    protected const DOCUMENTATION = 'docs/codebase/module-map.md';
}
