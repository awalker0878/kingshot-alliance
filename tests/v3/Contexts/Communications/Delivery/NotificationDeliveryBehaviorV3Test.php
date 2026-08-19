<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Communications\Delivery;

use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDeliveries;
use App\Contexts\Communications\Delivery\Actions\SaveNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\UpdateNotificationInboxState;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class NotificationDeliveryBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_external_endpoint_credentials_are_validated_and_encrypted(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);

        $endpointId = app(SaveNotificationEndpoint::class)->handle(
            $account->userId,
            $player->playerId,
            DeliveryChannel::Discord,
            'Alliance alerts',
            ['webhook_url' => 'https://discord.com/api/webhooks/123456789/secret-token_value'],
        );

        $endpoint = NotificationEndpoint::query()->findOrFail($endpointId);
        self::assertSame('Alliance alerts', $endpoint->label);
        self::assertSame(DeliveryChannel::Discord, $endpoint->channel);
        self::assertSame(
            'https://discord.com/api/webhooks/123456789/secret-token_value',
            $endpoint->configuration['webhook_url'],
        );
        self::assertStringNotContainsString('secret-token_value', (string) $endpoint->getRawOriginal('configuration'));

        $this->expectException(ValidationException::class);
        app(SaveNotificationEndpoint::class)->handle(
            $account->userId,
            $player->playerId,
            DeliveryChannel::Discord,
            'Unsafe endpoint',
            ['webhook_url' => 'https://example.test/api/webhooks/123456789/secret-token_value'],
        );
    }

    public function test_enabled_channels_fan_out_and_external_delivery_is_acknowledged(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        app(SaveNotificationEndpoint::class)->handle(
            $account->userId,
            $player->playerId,
            DeliveryChannel::Discord,
            'Alliance alerts',
            ['webhook_url' => 'https://discord.com/api/webhooks/123456789/secret-token_value'],
        );

        $deliveries = app(NotificationDeliveryService::class)->queueEnabledChannels(
            notificationType: 'event.reminder',
            recipientUserId: $account->userId,
            playerId: $player->playerId,
            dueAt: now()->subMinute(),
            idempotencyKey: 'test-event-reminder',
            metadata: [
                'title' => 'Bear Hunt',
                'body' => 'Starts in ten minutes.',
                'action_url' => '/events/01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ],
        );
        self::assertCount(2, $deliveries);

        Http::fake([
            'discord.com/*' => Http::response(null, 204),
        ]);
        self::assertSame(1, app(ProcessNotificationDeliveries::class)->handle());

        $external = NotificationDelivery::query()->where('channel', DeliveryChannel::Discord->value)->firstOrFail();
        self::assertSame(DeliveryStatus::Sent, $external->status);
        self::assertSame(1, $external->attempt_count);
        self::assertNotNull($external->sent_at);
        Http::assertSent(static fn ($request): bool => str_contains((string) $request['content'], 'Bear Hunt')
            && $request['allowed_mentions']['parse'] === []);
    }

    public function test_rate_limits_schedule_a_bounded_retry_and_inbox_updates_are_owner_scoped(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $other = $scenarios->account();
        $player = $scenarios->player($account->userId);
        app(SaveNotificationEndpoint::class)->handle(
            $account->userId,
            $player->playerId,
            DeliveryChannel::Telegram,
            'Officer room',
            [
                'bot_token' => '123456789:abcdefghijklmnopqrstuvwxyz_ABCDE',
                'chat_id' => '-1001234567890',
            ],
        );
        app(NotificationDeliveryService::class)->queue(
            notificationType: 'king_perks.reminder',
            recipientUserId: $account->userId,
            playerId: $player->playerId,
            channel: DeliveryChannel::Telegram->value,
            dueAt: now()->subMinute(),
            idempotencyKey: hash('sha256', 'telegram-rate-limit'),
            metadata: ['title' => 'King appointment'],
            maxAttempts: 2,
        );

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'parameters' => ['retry_after' => 60],
            ], 429),
        ]);
        self::assertSame(1, app(ProcessNotificationDeliveries::class)->handle());

        $delivery = NotificationDelivery::query()->firstOrFail();
        self::assertSame(DeliveryStatus::Failed, $delivery->status);
        self::assertSame(1, $delivery->attempt_count);
        self::assertNotNull($delivery->next_attempt_at);

        app(UpdateNotificationInboxState::class)->markRead(
            (string) $delivery->id,
            $account->userId,
            $player->playerId,
        );
        self::assertNotNull($delivery->fresh()?->read_at);

        $this->expectException(ModelNotFoundException::class);
        app(UpdateNotificationInboxState::class)->dismiss(
            (string) $delivery->id,
            $other->userId,
            null,
        );
    }
}
