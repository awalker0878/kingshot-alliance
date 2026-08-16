<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Operations\Rallies;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Operations/Rallies';

    protected const SOURCES = [
        'app/Contexts/Operations/Rallies',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/operations/rallies.md';
}
