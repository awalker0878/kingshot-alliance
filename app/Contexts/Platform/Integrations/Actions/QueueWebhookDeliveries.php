<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Platform\Integrations\Contracts\WebhookEventCatalog;
use App\Contexts\Platform\Integrations\Models\WebhookFanout;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final readonly class QueueWebhookDeliveries
{
    public function __construct(private ProcessWebhookFanouts $fanouts) {}

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

        $encodedPayload = json_encode($event->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $oversized = strlen($encodedPayload) > 262144;
        $fingerprint = hash('sha256', $encodedPayload);
        $fanout = DB::transaction(function () use ($event, $oversized, $fingerprint): WebhookFanout {
            return WebhookFanout::query()->firstOrCreate(['source_message_id' => $event->messageId], [
                'alliance_id' => $event->allianceId,
                'event_type' => $event->eventType,
                'payload' => $oversized ? null : $event->payload,
                'payload_oversized' => $oversized,
                'payload_fingerprint' => $fingerprint,
                'occurred_at' => $event->occurredAt,
                'upper_subscription_id' => WebhookSubscription::query()
                    ->when($event->allianceId !== null, fn ($query) => $query->where('alliance_id', $event->allianceId))
                    ->max('id'),
            ]);
        });
        if ($fanout->alliance_id !== $event->allianceId || $fanout->event_type !== $event->eventType
            || ! hash_equals((string) $fanout->payload_fingerprint, $fingerprint)
            || $fanout->occurred_at !== $event->occurredAt) {
            throw new UnexpectedValueException('Webhook source identity was replayed with different facts.');
        }

        return $this->fanouts->page((string) $fanout->id, 25);
    }

    private function isExternallyContracted(string $eventType): bool
    {
        return WebhookEventCatalog::isPublic($eventType);
    }
}
