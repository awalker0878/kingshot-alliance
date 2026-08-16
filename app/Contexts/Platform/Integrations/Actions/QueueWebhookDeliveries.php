<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Platform\Integrations\Contracts\WebhookEventCatalog;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Jobs\DeliverWebhookJob;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;

final class QueueWebhookDeliveries
{
    public function handle(OutboxPublished $event): int
    {
        if ($event->allianceId === null || ! $this->isExternallyContracted($event->eventType)) {
            return 0;
        }

        $payload = [
            'id' => $event->messageId,
            'event' => $event->eventType,
            'occurred_at' => $event->occurredAt,
            'alliance_id' => $event->allianceId,
            'data' => $event->payload,
        ];
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $oversized = strlen($encoded) > 262144;
        $queued = 0;

        $subscriptions = WebhookSubscription::query()
            ->where('alliance_id', $event->allianceId)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! $subscription->receives($event->eventType)) {
                continue;
            }

            $delivery = WebhookDelivery::query()->firstOrCreate(
                ['idempotency_key' => 'webhook:'.$subscription->id.':'.$event->messageId],
                [
                    'alliance_id' => $event->allianceId,
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
