<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Accounts\Identity\Queries\VerifiedNotificationEmailQuery;
use App\Contexts\Communications\Delivery\Contracts\NotificationSourceAuthorization;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\Communications\Delivery\Services\ExternalDeliveryChannelRegistry;
use App\Contexts\Communications\Delivery\Services\NotificationAttemptEligibility;
use App\Contexts\Communications\Delivery\Services\NotificationEndpointHealth;
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
        private NotificationAttemptEligibility $eligibility,
        private NotificationEndpointHealth $health,
        private NotificationSourceAuthorization $sourceAuthorization,
    ) {}

    public function handle(int $limit = 100): int
    {
        $now = CarbonImmutable::now('UTC');
        $ids = NotificationDelivery::query()
            ->where('digest_cadence', DigestCadence::Immediate->value)
            ->where('channel', '!=', DeliveryChannel::InApp->value)
            ->tap(fn ($query) => $this->eligibility->constrain($query, $now))
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit(max(1, min(1000, $limit)))
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $processed = 0;
        foreach ($ids as $deliveryId) {
            $attempt = $this->claim($deliveryId);
            if (! $attempt instanceof DeliveryAttempt) {
                continue;
            }

            $outcome = $this->deliver($attempt);
            $this->complete($attempt, $outcome);
            $processed++;
        }

        return $processed;
    }

    private function claim(string $deliveryId): ?DeliveryAttempt
    {
        return DB::transaction(function () use ($deliveryId): ?DeliveryAttempt {
            $now = CarbonImmutable::now('UTC');
            $delivery = NotificationDelivery::query()->whereKey($deliveryId)
                ->where('digest_cadence', DigestCadence::Immediate->value)
                ->where('channel', '!=', DeliveryChannel::InApp->value)
                ->tap(fn ($query) => $this->eligibility->constrain($query, $now))
                ->lockForUpdate()->first();
            if (! $delivery instanceof NotificationDelivery) {
                return null;
            }
            if ($delivery->attempt_count >= $delivery->max_attempts) {
                $this->exhausted($delivery);

                return null;
            }

            $message = NotificationMessage::query()
                ->whereKey($delivery->notification_message_id)
                ->first();
            if (! $message instanceof NotificationMessage) {
                $this->cancel($delivery, 'Notification message no longer exists.');

                return null;
            }

            if (! $this->sourceAuthorization->allows($message->source())) {
                $this->cancel($delivery, 'Notification source no longer authorizes this recipient.');

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

            return $this->attempt($delivery, $message);
        });
    }

    private function attempt(NotificationDelivery $delivery, NotificationMessage $message): DeliveryAttempt
    {
        return new DeliveryAttempt(
            deliveryId: (string) $delivery->id,
            messageId: (string) $message->id,
            recipientUserId: (int) $message->recipient_user_id,
            playerId: $message->player_id,
            channel: $delivery->channel,
            endpointId: $delivery->notification_endpoint_id,
            attemptCount: (int) $delivery->attempt_count,
            maxAttempts: (int) $delivery->max_attempts,
            notificationType: (string) $message->notification_type,
            messageTitle: (string) $message->title,
            messageBody: $message->body,
            messageActionUrl: $message->action_url,
            metadata: is_array($message->metadata) ? $message->metadata : [],
        );
    }

    /** Called only for a due, row-locked exhausted generation. No provider IO. */
    private function exhausted(NotificationDelivery $delivery): void
    {
        $outcome = DeliveryOutcome::failed(NotificationAttemptEligibility::EXHAUSTED_REASON, false);
        $delivery->forceFill([
            'status' => DeliveryStatus::Failed,
            'failed_at' => now(),
            'next_attempt_at' => null,
            'last_error' => $outcome->error,
        ])->save();
        $message = NotificationMessage::query()->whereKey($delivery->notification_message_id)->first();
        if ($message instanceof NotificationMessage) {
            $this->recordBroadcastOutcome($this->attempt($delivery, $message), $delivery, $outcome, false, exhausted: true);
        }
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

        return $provider->deliver($attempt, $configuration);
    }

    private function complete(DeliveryAttempt $attempt, DeliveryOutcome $outcome): void
    {
        DB::transaction(function () use ($attempt, $outcome): void {
            $endpoint = $this->health->lockForAttempt($attempt);
            $delivery = NotificationDelivery::query()
                ->whereKey($attempt->deliveryId)
                ->where('status', DeliveryStatus::Pending->value)
                ->where('attempt_count', $attempt->attemptCount)
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
            $this->health->record($endpoint, $outcome);
        });
    }

    private function recordBroadcastOutcome(
        DeliveryAttempt $attempt,
        NotificationDelivery $delivery,
        DeliveryOutcome $outcome,
        bool $retryable,
        bool $exhausted = false,
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
            'broadcast-delivery:'.$delivery->id.':attempt:'.$attempt->attemptCount.($exhausted ? ':exhausted' : ''),
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
