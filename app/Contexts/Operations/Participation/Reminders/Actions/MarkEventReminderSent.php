<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Actions;

use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;

final readonly class MarkEventReminderSent
{
    public function __construct(private NotificationDeliveryService $deliveries) {}

    public function handle(OutboxPublished $event): void
    {
        if ($event->eventType !== 'event.reminder.requested') {
            return;
        }
        $deliveryId = $event->payload['delivery_id'] ?? null;
        if (! is_string($deliveryId) || $deliveryId === '') {
            return;
        }
        $this->deliveries->markSent($deliveryId);
    }
}
