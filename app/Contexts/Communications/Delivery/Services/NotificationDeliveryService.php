<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Services;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationPreference;
use App\Contexts\Communications\Delivery\ValueObjects\QueuedDeliveryBatch;
use DateTimeInterface;
use Illuminate\Support\Carbon;

final class NotificationDeliveryService
{
    /** @param  array<string, mixed>  $metadata */
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
        $queuedAt = now();
        $isInApp = $channel === DeliveryChannel::InApp->value;

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
                'status' => $isInApp ? DeliveryStatus::Sent : DeliveryStatus::Queued,
                'attempt_count' => 0,
                'max_attempts' => max(1, $maxAttempts),
                'queued_at' => $queuedAt,
                'sent_at' => $isInApp ? $queuedAt : null,
                'metadata' => $metadata === [] ? null : $metadata,
            ],
        );
    }

    /**
     * Queue one idempotent delivery per enabled and configured channel.
     *
     * @param  array<string, mixed>  $metadata
     * @return list<NotificationDelivery>
     */
    public function queueEnabledChannels(
        string $notificationType,
        int $recipientUserId,
        ?string $playerId,
        DateTimeInterface $dueAt,
        string $idempotencyKey,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $metadata = [],
        int $maxAttempts = 5,
    ): array {
        $deliveries = [];
        foreach ($this->enabledChannels($recipientUserId, $playerId, $notificationType) as $channel) {
            $deliveries[] = $this->queue(
                notificationType: $notificationType,
                recipientUserId: $recipientUserId,
                playerId: $playerId,
                channel: $channel->value,
                dueAt: $dueAt,
                idempotencyKey: hash('sha256', $idempotencyKey.':'.$channel->value),
                subjectType: $subjectType,
                subjectId: $subjectId,
                metadata: $metadata,
                maxAttempts: $maxAttempts,
            );
        }

        return $deliveries;
    }

    /**
     * Cross-context delivery contract that exposes scalar results instead of
     * Communications-owned persistence models.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function queueEnabledChannelBatch(
        string $notificationType,
        int $recipientUserId,
        ?string $playerId,
        DateTimeInterface $dueAt,
        string $idempotencyKey,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $metadata = [],
        int $maxAttempts = 5,
    ): QueuedDeliveryBatch {
        $deliveries = $this->queueEnabledChannels(
            $notificationType,
            $recipientUserId,
            $playerId,
            $dueAt,
            $idempotencyKey,
            $subjectType,
            $subjectId,
            $metadata,
            $maxAttempts,
        );

        return new QueuedDeliveryBatch(
            array_values(array_map(
                static fn (NotificationDelivery $delivery): string => (string) $delivery->id,
                $deliveries,
            )),
            array_values(array_unique(array_map(
                static fn (NotificationDelivery $delivery): string => (string) $delivery->channel,
                $deliveries,
            ))),
            array_values(array_map(
                static fn (NotificationDelivery $delivery): string => (string) $delivery->id,
                array_filter(
                    $deliveries,
                    static fn (NotificationDelivery $delivery): bool => $delivery->wasRecentlyCreated,
                ),
            )),
        );
    }

    /**
     * Queue one account-wide delivery per enabled channel while evaluating the
     * preferences and endpoints of every eligible Governor. In-app deliveries
     * remain visible across Governor switches; external deliveries retain the
     * first eligible Governor that can route the selected channel.
     *
     * @param  non-empty-list<string>  $eligiblePlayerIds
     * @param  array<string, mixed>  $metadata
     */
    public function queueEnabledAccountChannelBatch(
        string $notificationType,
        int $recipientUserId,
        array $eligiblePlayerIds,
        DateTimeInterface $dueAt,
        string $idempotencyKey,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $metadata = [],
        int $maxAttempts = 5,
    ): QueuedDeliveryBatch {
        /** @var array<string, string> $channelRoutes */
        $channelRoutes = [];
        foreach (array_values(array_unique($eligiblePlayerIds)) as $playerId) {
            foreach ($this->enabledChannels($recipientUserId, $playerId, $notificationType) as $channel) {
                $channelRoutes[$channel->value] ??= $playerId;
            }
        }

        $deliveries = [];
        foreach ($channelRoutes as $channel => $routePlayerId) {
            $deliveries[] = $this->queue(
                notificationType: $notificationType,
                recipientUserId: $recipientUserId,
                playerId: $channel === DeliveryChannel::InApp->value ? null : $routePlayerId,
                channel: $channel,
                dueAt: $dueAt,
                idempotencyKey: hash('sha256', $idempotencyKey.':'.$channel),
                subjectType: $subjectType,
                subjectId: $subjectId,
                metadata: $metadata,
                maxAttempts: $maxAttempts,
            );
        }

        return new QueuedDeliveryBatch(
            array_values(array_map(
                static fn (NotificationDelivery $delivery): string => (string) $delivery->id,
                $deliveries,
            )),
            array_keys($channelRoutes),
            array_values(array_map(
                static fn (NotificationDelivery $delivery): string => (string) $delivery->id,
                array_filter(
                    $deliveries,
                    static fn (NotificationDelivery $delivery): bool => $delivery->wasRecentlyCreated,
                ),
            )),
        );
    }

    /** @return list<DeliveryChannel> */
    public function enabledChannels(int $recipientUserId, ?string $playerId, string $notificationType): array
    {
        $channels = [DeliveryChannel::InApp];
        $external = NotificationEndpoint::query()
            ->where('recipient_user_id', $recipientUserId)
            ->where('enabled', true)
            ->where(static function ($query) use ($playerId): void {
                $query->whereNull('player_id');
                if ($playerId !== null) {
                    $query->orWhere('player_id', $playerId);
                }
            })
            ->get()
            ->map(static fn (NotificationEndpoint $endpoint): DeliveryChannel => $endpoint->channel)
            ->unique(static fn (DeliveryChannel $channel): string => $channel->value)
            ->values()
            ->all();

        foreach ($external as $channel) {
            if ($channel->isExternal()) {
                $channels[] = $channel;
            }
        }

        return array_values(array_filter(
            $channels,
            fn (DeliveryChannel $channel): bool => $this->isEnabled(
                $recipientUserId,
                $playerId,
                $notificationType,
                $channel->value,
            ),
        ));
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
