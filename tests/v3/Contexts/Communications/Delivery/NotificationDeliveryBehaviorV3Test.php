<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Communications\Delivery;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Communications\Delivery\Actions\BulkUpdateNotificationInbox;
use App\Contexts\Communications\Delivery\Actions\PreviewNotificationInboxBulkAction;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDeliveries;
use App\Contexts\Communications\Delivery\Actions\SaveNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\UpdateNotificationInboxState;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
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
        $alliance = $scenarios->alliance($player);
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
                'alliance_id' => $alliance->allianceId,
                'broadcast_run_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
                'content_item_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAX',
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
        $outbox = OutboxMessage::query()->where('event_type', 'broadcast.delivery.succeeded')->firstOrFail();
        self::assertSame($alliance->allianceId, $outbox->alliance_id);
        self::assertSame('discord', $outbox->payload['channel'] ?? null);
        self::assertArrayNotHasKey('recipient_user_id', $outbox->payload);
        self::assertArrayNotHasKey('last_error', $outbox->payload);
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

    public function test_scalar_delivery_batch_distinguishes_new_deliveries_from_idempotent_replays(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $service = app(NotificationDeliveryService::class);

        $first = $service->queueEnabledChannelBatch(
            notificationType: 'gift_code.expiring',
            recipientUserId: $account->userId,
            playerId: $player->playerId,
            dueAt: now(),
            idempotencyKey: hash('sha256', 'scalar-delivery-batch'),
        );
        $replay = $service->queueEnabledChannelBatch(
            notificationType: 'gift_code.expiring',
            recipientUserId: $account->userId,
            playerId: $player->playerId,
            dueAt: now(),
            idempotencyKey: hash('sha256', 'scalar-delivery-batch'),
        );

        self::assertTrue($first->hasCreatedDeliveries());
        self::assertFalse($replay->hasCreatedDeliveries());
        self::assertSame($first->deliveryIds, $replay->deliveryIds);
        self::assertSame(1, NotificationDelivery::query()->count());
    }

    public function test_bulk_inbox_updates_preview_state_revalidate_ownership_and_report_each_item(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $other = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $service = app(NotificationDeliveryService::class);
        $state = app(UpdateNotificationInboxState::class);

        $ready = $service->queue(
            'event.reminder',
            $account->userId,
            $player->playerId,
            DeliveryChannel::InApp->value,
            now(),
            hash('sha256', 'bulk-ready'),
            metadata: ['title' => 'Ready reminder'],
        );
        $alreadyRead = $service->queue(
            'event.reminder',
            $account->userId,
            $player->playerId,
            DeliveryChannel::InApp->value,
            now(),
            hash('sha256', 'bulk-read'),
            metadata: ['title' => 'Read reminder'],
        );
        $alreadyDismissed = $service->queue(
            'event.reminder',
            $account->userId,
            $player->playerId,
            DeliveryChannel::InApp->value,
            now(),
            hash('sha256', 'bulk-dismissed'),
            metadata: ['title' => 'Dismissed reminder'],
        );
        $foreign = $service->queue(
            'event.reminder',
            $other->userId,
            null,
            DeliveryChannel::InApp->value,
            now(),
            hash('sha256', 'bulk-foreign'),
            metadata: ['title' => 'Foreign reminder'],
        );
        $state->markRead((string) $alreadyRead->id, $account->userId, $player->playerId);
        $state->dismiss((string) $alreadyDismissed->id, $account->userId, $player->playerId);

        $deliveryIds = [
            (string) $ready->id,
            (string) $alreadyRead->id,
            (string) $alreadyDismissed->id,
            (string) $foreign->id,
        ];
        $preview = app(PreviewNotificationInboxBulkAction::class)->handle(
            $account->userId,
            $player->playerId,
            $deliveryIds,
            PreviewNotificationInboxBulkAction::MARK_READ,
        );

        self::assertSame(1, $preview['ready']);
        self::assertSame(3, $preview['blocked']);
        self::assertSame(
            ['ready', 'already-read', 'already-dismissed', 'notification-unavailable'],
            array_column($preview['items'], 'code'),
        );

        $actor = User::query()->findOrFail($account->userId);
        $result = app(BulkUpdateNotificationInbox::class)->handle(
            $actor,
            $account->userId,
            $player->playerId,
            $deliveryIds,
            PreviewNotificationInboxBulkAction::MARK_READ,
        )->toArray();

        self::assertSame(1, $result['succeeded']);
        self::assertSame(1, $result['failed']);
        self::assertSame(2, $result['skipped']);
        self::assertSame([(string) $foreign->id], $result['failedItemIds']);
        self::assertNotNull($ready->fresh()?->read_at);
        self::assertNull($foreign->fresh()?->read_at);

        $dismissResult = app(BulkUpdateNotificationInbox::class)->handle(
            $actor,
            $account->userId,
            $player->playerId,
            [(string) $ready->id],
            PreviewNotificationInboxBulkAction::DISMISS,
        )->toArray();
        self::assertSame(1, $dismissResult['succeeded']);
        self::assertNotNull($ready->fresh()?->dismissed_at);

        self::assertSame(
            2,
            AuditEvent::query()
                ->where('event', 'notification.deliveries.bulk_inbox_updated')
                ->where('actor_user_id', $account->userId)
                ->count(),
        );
    }
}
