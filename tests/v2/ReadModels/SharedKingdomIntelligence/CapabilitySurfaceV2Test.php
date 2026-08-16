<?php

declare(strict_types=1);

namespace Tests\v2\ReadModels\SharedKingdomIntelligence;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'ReadModels/SharedKingdomIntelligence';

    protected const SOURCES = [
        'app/ReadModels/SharedKingdomIntelligence',
    ];

    protected const DOCUMENTATION = 'docs/codebase/module-map.md';
}
