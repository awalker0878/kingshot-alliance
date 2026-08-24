<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Queries;

use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;

final readonly class EventDeliveryHealthQuery
{
    /**
     * The caller must authorize the Event occurrence before passing its scalar identity.
     * Communications owns provider delivery state but does not import Operations models.
     *
     * @return array{
     *   deliveryCount:int,
     *   pendingCount:int,
     *   queuedCount:int,
     *   sentCount:int,
     *   failedCount:int,
     *   cancelledCount:int,
     *   retryableFailedCount:int,
     *   exhaustedFailedCount:int
     * }
     */
    public function forEventOccurrence(string $occurrenceId): array
    {
        $deliveries = NotificationDelivery::query()
            ->where('notification_type', 'event.reminder')
            ->where('subject_type', 'event_occurrence')
            ->where('subject_id', $occurrenceId)
            ->get(['status', 'attempt_count', 'max_attempts']);
        $failed = $deliveries->filter(
            static fn (NotificationDelivery $delivery): bool => $delivery->status === DeliveryStatus::Failed,
        );

        return [
            'deliveryCount' => $deliveries->count(),
            'pendingCount' => $deliveries->where('status', DeliveryStatus::Pending)->count(),
            'queuedCount' => $deliveries->where('status', DeliveryStatus::Queued)->count(),
            'sentCount' => $deliveries->where('status', DeliveryStatus::Sent)->count(),
            'failedCount' => $failed->count(),
            'cancelledCount' => $deliveries->where('status', DeliveryStatus::Cancelled)->count(),
            'retryableFailedCount' => $failed->filter(
                static fn (NotificationDelivery $delivery): bool => $delivery->attempt_count < $delivery->max_attempts,
            )->count(),
            'exhaustedFailedCount' => $failed->filter(
                static fn (NotificationDelivery $delivery): bool => $delivery->attempt_count >= $delivery->max_attempts,
            )->count(),
        ];
    }
}
