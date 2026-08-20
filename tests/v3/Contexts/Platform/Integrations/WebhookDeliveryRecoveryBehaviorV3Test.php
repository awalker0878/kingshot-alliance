<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Platform\Integrations;

use App\Contexts\Platform\Integrations\Actions\CreateWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\QueueWebhookTestDelivery;
use App\Contexts\Platform\Integrations\Actions\RetryWebhookDelivery;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Jobs\DeliverWebhookJob;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class WebhookDeliveryRecoveryBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_queue_a_targeted_signed_test_delivery(): void
    {
        Queue::fake();
        [$allianceId, $playerId, $subscriptionId] = $this->subscription();

        $deliveryId = app(QueueWebhookTestDelivery::class)->handle($allianceId, $playerId, $subscriptionId);
        $delivery = WebhookDelivery::query()->findOrFail($deliveryId);

        self::assertSame(WebhookDeliveryStatus::Pending, $delivery->status);
        self::assertSame('integration.test', $delivery->event_type);
        self::assertSame($subscriptionId, $delivery->payload['data']['subscription_id'] ?? null);
        self::assertTrue(AuditEvent::query()->where('event', 'integration.webhook.test_queued')->where('subject_id', $deliveryId)->exists());
        Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->deliveryId === $deliveryId);
    }

    public function test_manager_can_retry_only_an_exhausted_delivery_with_an_active_subscription(): void
    {
        Queue::fake();
        [$allianceId, $playerId, $subscriptionId] = $this->subscription();
        $deliveryId = app(QueueWebhookTestDelivery::class)->handle($allianceId, $playerId, $subscriptionId);
        $failed = WebhookDelivery::query()->findOrFail($deliveryId);
        $failed->forceFill([
            'status' => WebhookDeliveryStatus::Failed,
            'attempts' => 5,
            'last_error' => 'Retry budget exhausted.',
        ])->save();
        Queue::fake();

        app(RetryWebhookDelivery::class)->handle($allianceId, $playerId, $deliveryId);
        $retried = WebhookDelivery::query()->findOrFail($deliveryId);

        self::assertSame(WebhookDeliveryStatus::Pending, $retried->status);
        self::assertSame(5, $retried->attempts);
        self::assertSame('Manual retry requested.', $retried->last_error);
        self::assertTrue(AuditEvent::query()->where('event', 'integration.webhook_delivery.retry_queued')->where('subject_id', $deliveryId)->exists());
        Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->deliveryId === $deliveryId);
    }

    public function test_pending_delivery_cannot_be_manually_retried(): void
    {
        Queue::fake();
        [$allianceId, $playerId, $subscriptionId] = $this->subscription();
        $deliveryId = app(QueueWebhookTestDelivery::class)->handle($allianceId, $playerId, $subscriptionId);

        $this->expectException(ValidationException::class);
        app(RetryWebhookDelivery::class)->handle($allianceId, $playerId, $deliveryId);
    }

    /** @return array{string, string, string} */
    private function subscription(): array
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $alliance = $scenarios->alliance($player);
        $issued = app(CreateWebhookSubscription::class)->handle(
            $alliance->allianceId,
            $player->playerId,
            'Recovery endpoint',
            'https://hooks.example.test/kingshot',
            ['event.created'],
        );

        return [$alliance->allianceId, $player->playerId, $issued->subscriptionId];
    }
}
