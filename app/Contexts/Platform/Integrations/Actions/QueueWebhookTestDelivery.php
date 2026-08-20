<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteAuthorization;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Jobs\DeliverWebhookJob;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class QueueWebhookTestDelivery
{
    public function __construct(
        private AllianceWriteAuthorization $allianceAuthority,
        private AuditRecorder $audit,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $subscriptionId): string
    {
        $deliveryId = DB::transaction(function () use ($allianceId, $actorPlayerId, $subscriptionId): string {
            [$currentAlliance, $currentActor] = $this->allianceAuthority->authorizeManagerActive($actorPlayerId, $allianceId);
            $subscription = WebhookSubscription::query()
                ->where('alliance_id', $currentAlliance->allianceId)
                ->lockForUpdate()
                ->findOrFail($subscriptionId);

            if (! $subscription->is_active || $subscription->revoked_at !== null) {
                throw ValidationException::withMessages(['webhook' => 'Only active webhook subscriptions can receive a test delivery.']);
            }

            $messageId = (string) Str::ulid();
            $occurredAt = now()->toIso8601String();
            $delivery = WebhookDelivery::query()->create([
                'alliance_id' => $currentAlliance->allianceId,
                'webhook_subscription_id' => $subscription->id,
                'source_message_id' => $messageId,
                'event_type' => 'integration.test',
                'payload' => [
                    'id' => $messageId,
                    'event' => 'integration.test',
                    'occurred_at' => $occurredAt,
                    'alliance_id' => $currentAlliance->allianceId,
                    'data' => [
                        'subscription_id' => (string) $subscription->id,
                        'requested_by_player_id' => $currentActor->playerId,
                    ],
                ],
                'status' => WebhookDeliveryStatus::Pending,
                'attempts' => 0,
                'available_at' => now(),
                'idempotency_key' => 'webhook-test:'.$subscription->id.':'.$messageId,
            ]);

            $this->audit->record('integration.webhook.test_queued', $currentActor, $delivery, $currentAlliance->allianceId, [
                'subscription_id' => $subscription->id,
            ]);

            return (string) $delivery->id;
        });

        DeliverWebhookJob::dispatch($deliveryId)->onQueue('integrations');

        return $deliveryId;
    }
}
