<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Operations\Access;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Operations/Access';

    protected const SOURCES = [
        'app/Contexts/Operations/Access',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/operations/authorization.md';
}
