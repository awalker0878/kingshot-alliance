<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Intelligence\Access;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Intelligence/Access';

    protected const SOURCES = [
        'app/Contexts/Intelligence/Access',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/intelligence/authorization.md';
}
