<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Intelligence\Ingestion;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Intelligence/Ingestion';

    protected const SOURCES = [
        'app/Contexts/Intelligence/Ingestion',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/intelligence/observations-and-ingestion.md';
}
