<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RetryFailedNotificationDeliveries
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  non-empty-list<string>  $deliveryIds
     * @param  array<string, bool|int|string>  $requiredMetadata
     * @return list<string>
     */
    public function handle(
        AuditActor $actor,
        ?string $allianceId,
        array $deliveryIds,
        string $notificationType,
        string $subjectType,
        string $subjectId,
        array $requiredMetadata = [],
    ): array {
        return DB::transaction(function () use (
            $actor,
            $allianceId,
            $deliveryIds,
            $notificationType,
            $subjectType,
            $subjectId,
            $requiredMetadata,
        ): array {
            $deliveries = NotificationDelivery::query()
                ->whereIn('id', array_values(array_unique($deliveryIds)))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $retried = [];

            foreach ($deliveries as $delivery) {
                if ($delivery->status !== DeliveryStatus::Failed
                    || $delivery->attempt_count >= $delivery->max_attempts) {
                    continue;
                }

                $message = NotificationMessage::query()
                    ->whereKey($delivery->notification_message_id)
                    ->where('notification_type', $notificationType)
                    ->where('subject_type', $subjectType)
                    ->where('subject_id', $subjectId)
                    ->lockForUpdate()
                    ->first();
                if (! $message instanceof NotificationMessage
                    || ! $this->metadataMatches(
                        is_array($message->metadata) ? $message->metadata : null,
                        $requiredMetadata,
                    )) {
                    continue;
                }

                $delivery->forceFill([
                    'status' => DeliveryStatus::Queued,
                    'due_at' => now(),
                    'queued_at' => now(),
                    'failed_at' => null,
                    'next_attempt_at' => null,
                    'last_error' => null,
                ])->save();
                $retried[] = (string) $delivery->id;
            }

            $this->audit->record(
                'notification.deliveries.retry_queued',
                $actor,
                null,
                $allianceId,
                [
                    'notification_type' => $notificationType,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'requested_delivery_ids' => $deliveryIds,
                    'retried_delivery_ids' => $retried,
                ],
            );

            return $retried;
        });
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, bool|int|string>  $required
     */
    private function metadataMatches(?array $metadata, array $required): bool
    {
        if ($required === []) {
            return true;
        }
        if ($metadata === null) {
            return false;
        }

        foreach ($required as $key => $value) {
            if (($metadata[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }
}
