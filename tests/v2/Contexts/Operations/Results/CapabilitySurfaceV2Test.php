<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Operations\Results;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Operations/Results';

    protected const SOURCES = [
        'app/Contexts/Operations/Results',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/operations/results.md';
}
