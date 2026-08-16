<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Alliance\Content;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Alliance/Content';

    protected const SOURCES = [
        'app/Contexts/Alliance/Content',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/alliance/content.md';
}
