<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\v3\TestCase;

final class GiftCodeMaintenanceCommandV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_command_runs_workspace_notification_and_reminder_sweeps(): void
    {
        $status = Artisan::call('gift-codes:maintain', ['--limit' => 10]);
        $payload = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $status);
        self::assertIsArray($payload);
        self::assertArrayHasKey('expiryNotifications', $payload);
        self::assertArrayHasKey('transitionNotifications', $payload);
        self::assertArrayHasKey('workspaceRemindersQueued', $payload);
        self::assertArrayHasKey('workspaceNotifications', $payload);
        self::assertSame(
            ['examined', 'queued', 'nextCursor'],
            array_keys($payload['workspaceNotifications']),
        );
    }
}
