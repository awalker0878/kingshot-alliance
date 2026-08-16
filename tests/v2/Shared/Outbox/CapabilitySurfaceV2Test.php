<?php

declare(strict_types=1);

namespace Tests\v2\Shared\Outbox;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Shared/Outbox';

    protected const SOURCES = [
        'app/Shared/Infrastructure/Messaging/Outbox',
    ];

    protected const DOCUMENTATION = 'docs/architecture/integration-model.md';
}
