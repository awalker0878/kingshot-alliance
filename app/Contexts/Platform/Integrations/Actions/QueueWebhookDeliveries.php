<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Platform\Integrations\Contracts\WebhookEventCatalog;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Jobs\DeliverWebhookJob;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use UnexpectedValueException;

final class QueueWebhookDeliveries
{
    public function handle(OutboxPublished $event): int
    {
        if (! $this->isExternallyContracted($event->eventType)) {
            return 0;
        }
        if (! WebhookEventCatalog::payloadIsValid($event->eventType, $event->payload)) {
            throw new UnexpectedValueException('Public webhook payload does not satisfy its registered contract.');
        }
        if ($event->allianceId === null && ! WebhookEventCatalog::isGlobal($event->eventType)) {
            throw new UnexpectedValueException('Alliance webhook event is missing its Alliance scope.');
        }
        if ($event->allianceId !== null && WebhookEventCatalog::isGlobal($event->eventType)) {
            throw new UnexpectedValueException('Global webhook event unexpectedly carries an Alliance scope.');
        }

        $queued = 0;

        $subscriptionQuery = WebhookSubscription::query()
            ->where('is_active', true)
            ->whereNull('revoked_at');
        if ($event->allianceId !== null) {
            $subscriptionQuery->where('alliance_id', $event->allianceId);
        }
        $subscriptions = $subscriptionQuery->orderBy('alliance_id')->orderBy('id')->get();

        foreach ($subscriptions as $subscription) {
            if (! $subscription->receives($event->eventType)) {
                continue;
            }

            $deliveryAllianceId = (string) $subscription->alliance_id;
            $payload = [
                'schema_version' => '1.0',
                'id' => $event->messageId,
                'event' => $event->eventType,
                'occurred_at' => $event->occurredAt,
                'alliance_id' => $deliveryAllianceId,
                'data' => $event->payload,
            ];
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $oversized = strlen($encoded) > 262144;

            $delivery = WebhookDelivery::query()->firstOrCreate(
                ['idempotency_key' => 'webhook:'.$subscription->id.':'.$event->messageId],
                [
                    'alliance_id' => $deliveryAllianceId,
                    'webhook_subscription_id' => $subscription->id,
                    'source_message_id' => $event->messageId,
                    'event_type' => $event->eventType,
                    'payload' => $oversized ? null : $payload,
                    'status' => $oversized ? WebhookDeliveryStatus::Failed : WebhookDeliveryStatus::Pending,
                    'attempts' => 0,
                    'available_at' => now(),
                    'last_error' => $oversized ? 'Webhook payload exceeded the 256 KiB delivery limit.' : null,
                ],
            );

            if ($delivery->wasRecentlyCreated && ! $oversized) {
                DeliverWebhookJob::dispatch((string) $delivery->id)->onQueue('integrations');
                $queued++;
            }
        }

        return $queued;
    }

    private function isExternallyContracted(string $eventType): bool
    {
        return WebhookEventCatalog::isPublic($eventType);
    }
}
