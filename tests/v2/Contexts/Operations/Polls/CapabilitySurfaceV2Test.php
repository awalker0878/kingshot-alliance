<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Operations\Polls;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Operations/Polls';

    protected const SOURCES = [
        'app/Contexts/Operations/Polls',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/operations/planning.md';
}
