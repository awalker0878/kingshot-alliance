<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Reminders\Actions;

use App\Contexts\Communications\Reminders\Enums\EventReminderDeliveryStatus;
use App\Contexts\Communications\Reminders\Models\KingPerkReminderDelivery;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;

final class MarkKingPerkReminderSent
{
    public function handle(OutboxPublished $event): void
    {
        if ($event->eventType !== 'king_perks.reminder.requested') {
            return;
        }

        $deliveryId = $event->payload['delivery_id'] ?? null;
        if (! is_string($deliveryId) || $deliveryId === '') {
            return;
        }

        KingPerkReminderDelivery::query()
            ->whereKey($deliveryId)
            ->whereIn('status', [EventReminderDeliveryStatus::Pending->value, EventReminderDeliveryStatus::Queued->value])
            ->update([
                'status' => EventReminderDeliveryStatus::Sent->value,
                'sent_at' => now(),
            ]);
    }
}
