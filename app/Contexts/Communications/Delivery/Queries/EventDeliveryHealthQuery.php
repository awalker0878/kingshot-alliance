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
     *     deliveryCount:int,
     *     pendingCount:int,
     *     queuedCount:int,
     *     sentCount:int,
     *     failedCount:int,
     *     cancelledCount:int,
     *     retryableFailedCount:int,
     *     exhaustedFailedCount:int
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
        $countStatus = static fn (DeliveryStatus $status): int => $deliveries->filter(
            static fn (NotificationDelivery $delivery): bool => $delivery->status === $status,
        )->count();

        return [
            'deliveryCount' => $deliveries->count(),
            'pendingCount' => $countStatus(DeliveryStatus::Pending),
            'queuedCount' => $countStatus(DeliveryStatus::Queued),
            'sentCount' => $countStatus(DeliveryStatus::Sent),
            'failedCount' => $failed->count(),
            'cancelledCount' => $countStatus(DeliveryStatus::Cancelled),
            'retryableFailedCount' => $failed->filter(
                static fn (NotificationDelivery $delivery): bool => $delivery->attempt_count < $delivery->max_attempts,
            )->count(),
            'exhaustedFailedCount' => $failed->filter(
                static fn (NotificationDelivery $delivery): bool => $delivery->attempt_count >= $delivery->max_attempts,
            )->count(),
        ];
    }
}
