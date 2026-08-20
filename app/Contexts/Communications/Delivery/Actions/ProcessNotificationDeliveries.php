<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Services\ExternalDeliveryChannelRegistry;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryAttempt;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryOutcome;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ProcessNotificationDeliveries
{
    public function __construct(
        private ExternalDeliveryChannelRegistry $channels,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(int $limit = 100): int
    {
        $now = CarbonImmutable::now('UTC');
        $ids = NotificationDelivery::query()
            ->whereIn('channel', [DeliveryChannel::Discord->value, DeliveryChannel::Telegram->value])
            ->where('due_at', '<=', $now)
            ->where(static function ($query) use ($now): void {
                $query->where(static function ($ready): void {
                    $ready->where('status', DeliveryStatus::Queued->value);
                })->orWhere(static function ($retry) use ($now): void {
                    $retry->where('status', DeliveryStatus::Failed->value)
                        ->whereNotNull('next_attempt_at')
                        ->where('next_attempt_at', '<=', $now);
                })->orWhere(static function ($stale) use ($now): void {
                    $stale->where('status', DeliveryStatus::Pending->value)
                        ->where('updated_at', '<=', $now->subMinutes(5));
                });
            })
            ->orderBy('due_at')
            ->limit(max(1, min(1000, $limit)))
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $processed = 0;
        foreach ($ids as $deliveryId) {
            $attempt = $this->claim($deliveryId, $now);
            if (! $attempt instanceof DeliveryAttempt) {
                continue;
            }

            $outcome = $this->deliver($attempt);
            $this->complete($attempt, $outcome);
            $processed++;
        }

        return $processed;
    }

    private function claim(string $deliveryId, CarbonImmutable $now): ?DeliveryAttempt
    {
        return DB::transaction(function () use ($deliveryId, $now): ?DeliveryAttempt {
            $delivery = NotificationDelivery::query()->whereKey($deliveryId)->lockForUpdate()->first();
            if (! $delivery instanceof NotificationDelivery
                || $delivery->status === DeliveryStatus::Sent
                || $delivery->attempt_count >= $delivery->max_attempts
                || $delivery->due_at?->isAfter($now)) {
                return null;
            }

            $eligible = $delivery->status === DeliveryStatus::Queued
                || ($delivery->status === DeliveryStatus::Failed && $delivery->next_attempt_at?->isBefore($now->addSecond()))
                || ($delivery->status === DeliveryStatus::Pending && $delivery->updated_at?->isBefore($now->subMinutes(5)));
            if (! $eligible) {
                return null;
            }

            $channel = DeliveryChannel::tryFrom($delivery->channel);
            if (! $channel instanceof DeliveryChannel || ! $channel->isExternal()) {
                return null;
            }

            $attemptCount = $delivery->attempt_count + 1;
            $delivery->forceFill([
                'status' => DeliveryStatus::Pending,
                'attempt_count' => $attemptCount,
                'next_attempt_at' => null,
                'last_error' => null,
            ])->save();

            return new DeliveryAttempt(
                deliveryId: (string) $delivery->id,
                recipientUserId: (int) $delivery->recipient_user_id,
                playerId: $delivery->player_id,
                channel: $channel,
                attemptCount: $attemptCount,
                maxAttempts: (int) $delivery->max_attempts,
                metadata: is_array($delivery->metadata) ? $delivery->metadata : [],
            );
        });
    }

    private function deliver(DeliveryAttempt $attempt): DeliveryOutcome
    {
        $endpoint = NotificationEndpoint::query()
            ->where('recipient_user_id', $attempt->recipientUserId)
            ->where('channel', $attempt->channel->value)
            ->where('enabled', true)
            ->where(static function ($query) use ($attempt): void {
                $query->whereNull('player_id');
                if ($attempt->playerId !== null) {
                    $query->orWhere('player_id', $attempt->playerId);
                }
            })
            ->orderByRaw('CASE WHEN player_id IS NULL THEN 1 ELSE 0 END')
            ->first();
        if (! $endpoint instanceof NotificationEndpoint) {
            return DeliveryOutcome::failed('No enabled endpoint is configured for this channel.', false);
        }

        $channel = $this->channels->for($attempt->channel);
        if ($channel === null) {
            return DeliveryOutcome::failed('No provider is registered for this channel.', false);
        }

        $outcome = $channel->deliver($attempt, $endpoint->configuration);
        $endpoint->forceFill([
            'last_verified_at' => $outcome->delivered ? now() : $endpoint->last_verified_at,
            'last_error' => $outcome->delivered ? null : mb_substr((string) $outcome->error, 0, 2000),
        ])->save();

        return $outcome;
    }

    private function complete(DeliveryAttempt $attempt, DeliveryOutcome $outcome): void
    {
        DB::transaction(function () use ($attempt, $outcome): void {
            $delivery = NotificationDelivery::query()
                ->whereKey($attempt->deliveryId)
                ->where('status', DeliveryStatus::Pending->value)
                ->lockForUpdate()
                ->first();
            if (! $delivery instanceof NotificationDelivery) {
                return;
            }

            $retryable = ! $outcome->delivered
                && $outcome->retryable
                && $attempt->attemptCount < $attempt->maxAttempts;
            $delivery->forceFill($outcome->delivered ? [
                'status' => DeliveryStatus::Sent,
                'sent_at' => now(),
                'failed_at' => null,
                'next_attempt_at' => null,
                'last_error' => null,
            ] : [
                'status' => DeliveryStatus::Failed,
                'failed_at' => now(),
                'next_attempt_at' => $retryable
                    ? ($outcome->retryAt ?? CarbonImmutable::now('UTC')->addSeconds(30 * (2 ** ($attempt->attemptCount - 1))))
                    : null,
                'last_error' => mb_substr((string) $outcome->error, 0, 2000),
            ])->save();

            $allianceId = $attempt->metadata['alliance_id'] ?? null;
            $runId = $attempt->metadata['broadcast_run_id'] ?? null;
            $contentItemId = $attempt->metadata['content_item_id'] ?? null;
            if (! is_string($allianceId) || $allianceId === ''
                || ! is_string($runId) || $runId === ''
                || ! is_string($contentItemId) || $contentItemId === '') {
                return;
            }

            $metadata = [
                'broadcast_run_id' => $runId,
                'content_item_id' => $contentItemId,
                'channel' => $attempt->channel->value,
                'status' => $delivery->status->value,
                'attempt_count' => $attempt->attemptCount,
                'retryable' => $retryable,
            ];
            $this->outbox->record(
                $outcome->delivered
                    ? 'broadcast.delivery.succeeded'
                    : 'broadcast.delivery.failed',
                $allianceId,
                $delivery,
                $metadata,
                'broadcast-delivery:'.$delivery->id.':attempt:'.$attempt->attemptCount,
                'alliance:'.$allianceId,
            );
        });
    }
}
