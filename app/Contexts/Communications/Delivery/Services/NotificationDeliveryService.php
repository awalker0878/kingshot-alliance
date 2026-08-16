<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Services;

use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationPreference;
use DateTimeInterface;
use Illuminate\Support\Carbon;

final class NotificationDeliveryService
{
    /** @param array<string, mixed> $metadata */
    public function queue(
        string $notificationType,
        int $recipientUserId,
        ?string $playerId,
        string $channel,
        DateTimeInterface $dueAt,
        string $idempotencyKey,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $metadata = [],
        int $maxAttempts = 5,
    ): NotificationDelivery {
        return NotificationDelivery::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'notification_type' => $notificationType,
                'recipient_user_id' => $recipientUserId,
                'player_id' => $playerId,
                'channel' => $channel,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'due_at' => $dueAt,
                'status' => DeliveryStatus::Queued,
                'attempt_count' => 0,
                'max_attempts' => max(1, $maxAttempts),
                'queued_at' => now(),
                'metadata' => $metadata === [] ? null : $metadata,
            ],
        );
    }

    public function markSent(string $deliveryId): void
    {
        NotificationDelivery::query()
            ->whereKey($deliveryId)
            ->whereIn('status', [DeliveryStatus::Pending->value, DeliveryStatus::Queued->value, DeliveryStatus::Failed->value])
            ->update([
                'status' => DeliveryStatus::Sent->value,
                'sent_at' => now(),
                'failed_at' => null,
                'next_attempt_at' => null,
                'last_error' => null,
            ]);
    }

    public function markFailed(string $deliveryId, string $error, ?DateTimeInterface $retryAt = null): void
    {
        $delivery = NotificationDelivery::query()->whereKey($deliveryId)->first();
        if (! $delivery instanceof NotificationDelivery || $delivery->status === DeliveryStatus::Sent) {
            return;
        }

        $attemptCount = (int) $delivery->attempt_count + 1;
        $retryAllowed = $attemptCount < (int) $delivery->max_attempts;

        $delivery->forceFill([
            'status' => DeliveryStatus::Failed,
            'attempt_count' => $attemptCount,
            'failed_at' => now(),
            'next_attempt_at' => $retryAllowed && $retryAt !== null ? Carbon::instance($retryAt) : null,
            'last_error' => mb_substr($error, 0, 2000),
        ])->save();
    }

    public function isEnabled(int $recipientUserId, ?string $playerId, string $notificationType, string $channel): bool
    {
        $preference = NotificationPreference::query()
            ->where('recipient_user_id', $recipientUserId)
            ->where('notification_type', $notificationType)
            ->where('channel', $channel)
            ->where(static function ($query) use ($playerId): void {
                if ($playerId === null) {
                    $query->whereNull('player_id');
                    return;
                }

                $query->where(static function ($playerQuery) use ($playerId): void {
                    $playerQuery->where('player_id', $playerId)->orWhereNull('player_id');
                });
            })
            ->orderByRaw('CASE WHEN player_id IS NULL THEN 1 ELSE 0 END')
            ->first();

        return ! $preference instanceof NotificationPreference || $preference->enabled;
    }
}
