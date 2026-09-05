<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Accounts\Identity\Queries\VerifiedNotificationEmailQuery;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Enums\EndpointHealthStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\Communications\Delivery\Services\ExternalDeliveryChannelRegistry;
use App\Contexts\Communications\Delivery\Services\NotificationRouteResolver;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryAttempt;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryOutcome;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\Communications\Delivery\ValueObjects\ResolvedDeliveryRoute;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ProcessNotificationDeliveries
{
    public function __construct(
        private ExternalDeliveryChannelRegistry $channels,
        private NotificationRouteResolver $routes,
        private VerifiedNotificationEmailQuery $email,
        private PlayerReferenceQuery $players,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(int $limit = 100): int
    {
        $now = CarbonImmutable::now('UTC');
        $ids = NotificationDelivery::query()
            ->where('digest_cadence', DigestCadence::Immediate->value)
            ->where('channel', '!=', DeliveryChannel::InApp->value)
            ->where('due_at', '<=', $now)
            ->where(static function ($query) use ($now): void {
                $query->where('status', DeliveryStatus::Queued->value)
                    ->orWhere(static function ($retry) use ($now): void {
                        $retry->where('status', DeliveryStatus::Failed->value)
                            ->whereNotNull('next_attempt_at')
                            ->where('next_attempt_at', '<=', $now);
                    })->orWhere(static function ($stale) use ($now): void {
                        $stale->where('status', DeliveryStatus::Pending->value)
                            ->where('updated_at', '<=', $now->subMinutes(5));
                    });
            })
            ->orderBy('due_at')
            ->orderBy('id')
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
                || $delivery->status === DeliveryStatus::Cancelled
                || $delivery->digest_cadence !== DigestCadence::Immediate
                || $delivery->attempt_count >= $delivery->max_attempts
                || $delivery->due_at->isAfter($now)) {
                return null;
            }

            $eligible = $delivery->status === DeliveryStatus::Queued
                || ($delivery->status === DeliveryStatus::Failed
                    && $delivery->next_attempt_at?->isBefore($now->addSecond()))
                || ($delivery->status === DeliveryStatus::Pending
                    && $delivery->updated_at?->isBefore($now->subMinutes(5)));
            if (! $eligible) {
                return null;
            }

            $message = NotificationMessage::query()
                ->whereKey($delivery->notification_message_id)
                ->first();
            if (! $message instanceof NotificationMessage) {
                $this->cancel($delivery, 'Notification message no longer exists.');

                return null;
            }

            $channel = $delivery->channel;
            if (! $channel->isExternal()) {
                $this->cancel($delivery, 'Only external routes are processed by the provider worker.');

                return null;
            }

            $endpoint = null;
            if ($channel->usesStoredEndpoint()) {
                $endpoint = NotificationEndpoint::query()
                    ->whereKey($delivery->notification_endpoint_id)
                    ->where('recipient_user_id', $message->recipient_user_id)
                    ->first();
                if (! $endpoint instanceof NotificationEndpoint || ! $endpoint->enabled) {
                    $this->cancel($delivery, 'The selected notification destination is no longer enabled.');

                    return null;
                }
            }

            $routingPlayerId = $endpoint instanceof NotificationEndpoint
                ? $endpoint->player_id
                : $message->player_id;
            if ($routingPlayerId !== null
                && $this->players->findOwnedByUser((int) $message->recipient_user_id, $routingPlayerId) === null) {
                $this->cancel($delivery, 'The notification Governor is no longer owned by this account.');

                return null;
            }

            $resolved = $this->currentlyResolvedRoute($message, $delivery, $routingPlayerId, $now);
            if (! $resolved instanceof ResolvedDeliveryRoute) {
                $this->cancel($delivery, 'Recipient routing policy no longer permits this destination.');

                return null;
            }
            if ($resolved->digestCadence !== DigestCadence::Immediate || $resolved->dueAt->isAfter($now)) {
                $delivery->forceFill([
                    'digest_cadence' => $resolved->digestCadence,
                    'due_at' => $resolved->dueAt,
                    'status' => DeliveryStatus::Queued,
                    'next_attempt_at' => null,
                    'routing_reason' => $resolved->reason,
                    'last_error' => null,
                ])->save();

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
                messageId: (string) $message->id,
                recipientUserId: (int) $message->recipient_user_id,
                playerId: $message->player_id,
                channel: $channel,
                endpointId: $endpoint instanceof NotificationEndpoint ? (string) $endpoint->id : null,
                attemptCount: $attemptCount,
                maxAttempts: (int) $delivery->max_attempts,
                notificationType: (string) $message->notification_type,
                messageTitle: (string) $message->title,
                messageBody: $message->body,
                messageActionUrl: $message->action_url,
                metadata: is_array($message->metadata) ? $message->metadata : [],
            );
        });
    }

    private function currentlyResolvedRoute(
        NotificationMessage $message,
        NotificationDelivery $delivery,
        ?string $routingPlayerId,
        CarbonImmutable $now,
    ): ?ResolvedDeliveryRoute {
        $intent = new NotificationIntent(
            notificationType: (string) $message->notification_type,
            recipientUserId: (int) $message->recipient_user_id,
            playerId: $routingPlayerId,
            availableAt: $now,
            idempotencyKey: (string) $message->idempotency_key,
            title: (string) $message->title,
            body: $message->body,
            actionUrl: $message->action_url,
            subjectType: $message->subject_type,
            subjectId: $message->subject_id,
            urgency: $message->urgency,
            metadata: is_array($message->metadata) ? $message->metadata : [],
            maxAttempts: (int) $delivery->max_attempts,
        );

        foreach ($this->routes->resolve($intent)->routes as $route) {
            if ($route->channel !== $delivery->channel) {
                continue;
            }
            if ($delivery->channel->usesStoredEndpoint()
                && $route->endpointId !== $delivery->notification_endpoint_id) {
                continue;
            }

            return $route;
        }

        return null;
    }

    private function deliver(DeliveryAttempt $attempt): DeliveryOutcome
    {
        $configuration = [];
        $endpoint = null;
        if ($attempt->channel->usesStoredEndpoint()) {
            $endpoint = NotificationEndpoint::query()->whereKey($attempt->endpointId)->first();
            if (! $endpoint instanceof NotificationEndpoint || ! $endpoint->enabled) {
                return DeliveryOutcome::failed('The selected destination is no longer enabled.', false);
            }
            $configuration = $endpoint->configuration;
        } elseif ($attempt->channel === DeliveryChannel::Email) {
            $email = $this->email->forUser($attempt->recipientUserId);
            if ($email === null) {
                return DeliveryOutcome::failed('A verified notification email is no longer available.', false);
            }
            $configuration = ['email' => $email];
        }

        $provider = $this->channels->for($attempt->channel);
        if ($provider === null) {
            return DeliveryOutcome::failed('No provider is registered for this channel.', false);
        }

        $outcome = $provider->deliver($attempt, $configuration);
        if ($endpoint instanceof NotificationEndpoint) {
            $endpoint->forceFill($outcome->delivered ? [
                'health_status' => EndpointHealthStatus::Healthy,
                'last_verified_at' => now(),
                'last_successful_delivery_at' => now(),
                'consecutive_failures' => 0,
                'last_error' => null,
            ] : [
                'health_status' => EndpointHealthStatus::Degraded,
                'last_failed_delivery_at' => now(),
                'consecutive_failures' => min(1000000, (int) $endpoint->consecutive_failures + 1),
                'last_error' => mb_substr((string) $outcome->error, 0, 2000),
            ])->save();
        }

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

            $this->recordBroadcastOutcome($attempt, $delivery, $outcome, $retryable);
        });
    }

    private function recordBroadcastOutcome(
        DeliveryAttempt $attempt,
        NotificationDelivery $delivery,
        DeliveryOutcome $outcome,
        bool $retryable,
    ): void {
        $allianceId = $attempt->metadata['alliance_id'] ?? null;
        $runId = $attempt->metadata['broadcast_run_id'] ?? null;
        $contentItemId = $attempt->metadata['content_item_id'] ?? null;
        if (! is_string($allianceId) || $allianceId === ''
            || ! is_string($runId) || $runId === ''
            || ! is_string($contentItemId) || $contentItemId === '') {
            return;
        }

        $this->outbox->record(
            $outcome->delivered ? 'broadcast.delivery.succeeded' : 'broadcast.delivery.failed',
            $allianceId,
            $delivery,
            [
                'broadcast_run_id' => $runId,
                'content_item_id' => $contentItemId,
                'channel' => $attempt->channel->value,
                'status' => $delivery->status->value,
                'attempt_count' => $attempt->attemptCount,
                'retryable' => $retryable,
            ],
            'broadcast-delivery:'.$delivery->id.':attempt:'.$attempt->attemptCount,
            'alliance:'.$allianceId,
        );
    }

    private function cancel(NotificationDelivery $delivery, string $reason): void
    {
        $delivery->forceFill([
            'status' => DeliveryStatus::Cancelled,
            'next_attempt_at' => null,
            'last_error' => mb_substr($reason, 0, 2000),
        ])->save();
    }
}
