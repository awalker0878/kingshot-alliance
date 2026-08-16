<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Intelligence\EventAnalysis;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Intelligence/EventAnalysis';

    protected const SOURCES = [
        'app/Contexts/Intelligence/EventAnalysis',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/intelligence/event-analysis.md';
}
