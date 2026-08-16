<?php

declare(strict_types=1);

namespace Tests\v2\Shared\AuditTrail;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Shared/AuditTrail';

    protected const SOURCES = [
        'app/Shared/Infrastructure/AuditTrail',
    ];

    protected const DOCUMENTATION = 'docs/architecture/integration-model.md';
}
