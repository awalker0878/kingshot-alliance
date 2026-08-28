<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\CommandOverview;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\GameWorld\GiftCodes\Actions\SubmitGiftCode;
use App\ReadModels\CommandOverview\Queries\CommandOverviewQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class CommandOverviewBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_overview_surfaces_only_the_active_governors_actionable_work(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $other = $scenarios->account();
        $otherPlayer = $scenarios->player($other->userId);

        app(SubmitGiftCode::class)->handle($player, [
            'code' => 'COMMAND-CENTER',
            'source_type' => 'official',
        ]);

        $deliveries = app(NotificationDeliveryService::class);
        $notification = $deliveries->queue(
            notificationType: 'event.reminder',
            recipientUserId: $account->userId,
            playerId: $player->playerId,
            channel: DeliveryChannel::InApp->value,
            dueAt: now()->subMinute(),
            idempotencyKey: hash('sha256', 'command-overview-notification'),
            metadata: ['title' => 'Alliance event'],
        );
        $deliveries->markSent((string) $notification->id);

        $otherNotification = $deliveries->queue(
            notificationType: 'event.reminder',
            recipientUserId: $other->userId,
            playerId: $otherPlayer->playerId,
            channel: DeliveryChannel::InApp->value,
            dueAt: now()->subMinute(),
            idempotencyKey: hash('sha256', 'other-command-overview-notification'),
        );
        $deliveries->markSent((string) $otherNotification->id);

        $overview = app(CommandOverviewQuery::class)->for(
            $account->userId,
            $player,
            null,
            false,
        );

        self::assertSame(1, $overview['unreadNotifications']);
        self::assertSame(1, $overview['pendingGiftCodes']);
        self::assertSame(2, $overview['actionCount']);
        self::assertSame([], $overview['intelligenceSignals']);
        self::assertSame('COMMAND-CENTER', $overview['giftCodes'][0]['code']);
        self::assertSame([], $overview['upcomingEvents']);
        self::assertNull($overview['recruitment']);
    }
}
