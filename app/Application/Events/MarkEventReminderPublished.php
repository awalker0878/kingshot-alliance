<?php

declare(strict_types=1);

namespace App\Application\Events;

use App\Domain\Events\Enums\EventReminderDeliveryStatus;
use App\Domain\Shared\Events\OutboxPublished;
use App\Models\EventReminderDelivery;

final class MarkEventReminderPublished
{
    public function handle(OutboxPublished $event): void
    {
        if ($event->eventType !== 'event.reminder.requested') {
            return;
        }

        $deliveryId = $event->payload['delivery_id'] ?? null;

        if (! is_string($deliveryId) || $deliveryId === '') {
            return;
        }

        EventReminderDelivery::query()
            ->whereKey($deliveryId)
            ->where('alliance_id', $event->allianceId)
            ->where('status', EventReminderDeliveryStatus::Queued->value)
            ->update([
                'status' => EventReminderDeliveryStatus::Sent->value,
                'sent_at' => now(),
                'last_error' => null,
            ]);
    }
}
