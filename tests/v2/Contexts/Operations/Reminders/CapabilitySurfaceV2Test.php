<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Operations\Reminders;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Operations/Reminders';

    protected const SOURCES = [
        'app/Contexts/Operations/Participation/Reminders',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/operations/reminders.md';
}
