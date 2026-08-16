<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Alliance\Recruitment;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Alliance/Recruitment';

    protected const SOURCES = [
        'app/Contexts/Alliance/Recruitment',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/alliance/recruitment.md';
}
