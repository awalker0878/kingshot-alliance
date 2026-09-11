<?php

declare(strict_types=1);

namespace Tests\Workflows\NotificationDelivery\Architecture;

use App\Contexts\Communications\Delivery\Contracts\NotificationSourceAuthorization;
use App\Workflows\NotificationDelivery\Services\CurrentNotificationSourceAuthorization;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class NotificationQueueRegistrationTest extends TestCase
{
    public function test_source_authorization_is_bound_to_the_current_owner_composition(): void
    {
        self::assertInstanceOf(CurrentNotificationSourceAuthorization::class, app(NotificationSourceAuthorization::class));
    }

    public function test_queue_commands_and_scheduler_are_registered_with_bounded_cursors(): void
    {
        $commands = Artisan::all();
        self::assertArrayHasKey('notifications:queue-officer-briefs', $commands);
        self::assertArrayHasKey('notifications:queue-intelligence-changes', $commands);

        $source = (string) file_get_contents(base_path('routes/console.php'));
        self::assertStringContainsString('notifications:queue-officer-briefs --group=daily --limit=1000 --cycle', $source);
        self::assertStringContainsString('notifications:queue-officer-briefs --group=event --limit=1000 --cycle', $source);
        self::assertStringContainsString('notifications:queue-intelligence-changes --limit=1000 --cycle', $source);
        self::assertStringContainsString('->everyFifteenMinutes()', $source);
    }
}
