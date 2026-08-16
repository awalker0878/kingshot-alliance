<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Reminders\Actions;

use App\Contexts\Communications\Reminders\Enums\EventReminderDeliveryStatus;
use App\Contexts\Communications\Reminders\Models\EventReminderDelivery;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;

final class MarkEventReminderSent
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
            ->whereIn('status', [EventReminderDeliveryStatus::Pending->value, EventReminderDeliveryStatus::Queued->value])
            ->update([
                'status' => EventReminderDeliveryStatus::Sent->value,
                'sent_at' => now(),
                'last_error' => null,
            ]);
    }
}
