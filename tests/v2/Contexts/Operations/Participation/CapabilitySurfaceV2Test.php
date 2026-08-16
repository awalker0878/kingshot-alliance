<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Operations\Participation;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Operations/Participation';

    protected const SOURCES = [
        'app/Contexts/Operations/Participation',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/operations/participation.md';
}
