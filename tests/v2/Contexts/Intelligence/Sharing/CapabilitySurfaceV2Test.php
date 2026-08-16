<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Intelligence\Sharing;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Intelligence/Sharing';

    protected const SOURCES = [
        'app/Contexts/Intelligence/Sharing',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/intelligence/diplomacy-and-sharing.md';
}
