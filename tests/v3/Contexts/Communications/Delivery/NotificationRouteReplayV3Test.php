<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Communications\Delivery;

use App\Contexts\Communications\Delivery\Actions\SetNotificationPreference;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class NotificationRouteReplayV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_replay_adds_only_newly_eligible_routes_without_duplicating_the_message(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $preferences = app(SetNotificationPreference::class);
        $deliveries = app(NotificationDeliveryService::class);

        $preferences->handle(
            $account->userId,
            $player->playerId,
            'event.reminder',
            DeliveryChannel::InApp,
            false,
        );

        $intent = NotificationIntent::fromScalars(
            notificationType: 'event.reminder',
            recipientUserId: $account->userId,
            playerId: $player->playerId,
            availableAt: now(),
            idempotencyKey: 'route-replay-preference-change',
            title: 'Alliance event',
        );

        $suppressed = $deliveries->queue($intent);
        self::assertTrue($suppressed->createdMessage);
        self::assertFalse($suppressed->hasCreatedDeliveries());
        self::assertSame(0, $suppressed->count());
        self::assertSame(1, NotificationMessage::query()->count());
        self::assertSame(0, NotificationDelivery::query()->count());

        $preferences->handle(
            $account->userId,
            $player->playerId,
            'event.reminder',
            DeliveryChannel::InApp,
            true,
        );

        $enabled = $deliveries->queue($intent);
        self::assertFalse($enabled->createdMessage);
        self::assertTrue($enabled->hasCreatedDeliveries());
        self::assertSame($suppressed->messageId, $enabled->messageId);
        self::assertCount(1, $enabled->createdDeliveryIds);
        self::assertSame([DeliveryChannel::InApp->value], $enabled->channels);
        self::assertSame(1, NotificationMessage::query()->count());
        self::assertSame(1, NotificationDelivery::query()->count());
        self::assertSame(
            DeliveryStatus::Sent,
            NotificationDelivery::query()->firstOrFail()->status,
        );

        $replay = $deliveries->queue($intent);
        self::assertFalse($replay->createdMessage);
        self::assertFalse($replay->hasCreatedDeliveries());
        self::assertSame($enabled->deliveryIds, $replay->deliveryIds);
        self::assertSame(1, NotificationMessage::query()->count());
        self::assertSame(1, NotificationDelivery::query()->count());
    }
}
