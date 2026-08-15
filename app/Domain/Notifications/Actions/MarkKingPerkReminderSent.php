<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Events\Enums\EventReminderDeliveryStatus;
use App\Domain\Notifications\Models\KingPerkReminderDelivery;
use App\Domain\Platform\Events\OutboxPublished;

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
