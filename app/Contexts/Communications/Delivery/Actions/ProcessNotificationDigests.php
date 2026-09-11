<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Accounts\Identity\Queries\VerifiedNotificationEmailQuery;
use App\Contexts\Communications\Delivery\Contracts\NotificationSourceAuthorization;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationDigestDispatch;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\Communications\Delivery\Services\ExternalDeliveryChannelRegistry;
use App\Contexts\Communications\Delivery\Services\NotificationAttemptEligibility;
use App\Contexts\Communications\Delivery\Services\NotificationEndpointHealth;
use App\Contexts\Communications\Delivery\Services\NotificationRouteResolver;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryAttempt;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryOutcome;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ProcessNotificationDigests
{
    private const MAX_MEMBERS = 20;

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
        $ids = NotificationDigestDispatch::query()
            ->tap(fn ($query) => $this->eligibility->constrain($query, $now))
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)))
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $processed = 0;
        foreach ($ids as $id) {
            $attempt = $this->claim($id);
            if (! $attempt instanceof DeliveryAttempt) {
                continue;
            }
            $outcome = $this->deliver($attempt);
            $this->complete($attempt, $outcome);
            $processed++;
        }

        return $processed;
    }

    private function claim(string $dispatchId): ?DeliveryAttempt
    {
        return DB::transaction(function () use ($dispatchId): ?DeliveryAttempt {
            $now = CarbonImmutable::now('UTC');
            $dispatch = NotificationDigestDispatch::query()->whereKey($dispatchId)
                ->tap(fn ($query) => $this->eligibility->constrain($query, $now))
                ->lockForUpdate()->first();
            if (! $dispatch instanceof NotificationDigestDispatch) {
                return null;
            }
            if ($dispatch->attempt_count >= $dispatch->max_attempts) {
                $this->exhausted($dispatch);

                return null;
            }

            $memberIds = DB::table('notification_digest_members')
                ->where('notification_digest_dispatch_id', $dispatchId)
                ->orderBy('notification_delivery_id')
                ->limit(self::MAX_MEMBERS)
                ->pluck('notification_delivery_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();
            if ($memberIds === []) {
                $dispatch->forceFill(['status' => DeliveryStatus::Cancelled, 'last_error' => 'Digest has no eligible deliveries.'])->save();

                return null;
            }

            $active = [];
            $latestDue = $now;
            foreach ($memberIds as $deliveryId) {
                $delivery = NotificationDelivery::query()->whereKey($deliveryId)->lockForUpdate()->first();
                if (! $delivery instanceof NotificationDelivery || $delivery->status !== DeliveryStatus::Queued) {
                    DB::table('notification_digest_members')->where('notification_delivery_id', $deliveryId)->delete();

                    continue;
                }
                $message = NotificationMessage::query()->whereKey($delivery->notification_message_id)->first();
                if (! $message instanceof NotificationMessage) {
                    $this->cancelDelivery($delivery, 'Notification message no longer exists.');
                    DB::table('notification_digest_members')->where('notification_delivery_id', $deliveryId)->delete();

                    continue;
                }

                if (! $this->sourceAuthorization->allows($message->source())) {
                    $this->cancelDelivery($delivery, 'Notification source no longer authorizes this recipient.');
                    DB::table('notification_digest_members')->where('notification_delivery_id', $deliveryId)->delete();

                    continue;
                }

                $endpoint = null;
                if ($delivery->channel->usesStoredEndpoint()) {
                    $endpoint = NotificationEndpoint::query()
                        ->whereKey($delivery->notification_endpoint_id)
                        ->where('recipient_user_id', $message->recipient_user_id)
                        ->first();
                    if (! $endpoint instanceof NotificationEndpoint || ! $endpoint->enabled) {
                        $this->cancelDelivery($delivery, 'The selected notification destination is no longer enabled.');
                        DB::table('notification_digest_members')->where('notification_delivery_id', $deliveryId)->delete();

                        continue;
                    }
                }

                $routingPlayerId = $endpoint instanceof NotificationEndpoint
                    ? $endpoint->player_id
                    : $message->player_id;
                if ($routingPlayerId !== null
                    && $this->players->findOwnedByUser((int) $message->recipient_user_id, $routingPlayerId) === null) {
                    $this->cancelDelivery($delivery, 'The notification Governor is no longer owned by this account.');
                    DB::table('notification_digest_members')->where('notification_delivery_id', $deliveryId)->delete();

                    continue;
                }

                $intent = new NotificationIntent(
                    notificationType: (string) $message->notification_type,
                    recipientUserId: (int) $message->recipient_user_id,
                    playerId: $routingPlayerId,
                    availableAt: $message->available_at,
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
                $resolved = null;
                foreach ($this->routes->resolve($intent)->routes as $route) {
                    if ($route->channel === $delivery->channel
                        && (! $delivery->channel->usesStoredEndpoint() || $route->endpointId === $delivery->notification_endpoint_id)) {
                        $resolved = $route;
                        break;
                    }
                }
                if ($resolved === null) {
                    $this->cancelDelivery($delivery, 'Recipient routing policy no longer permits this destination.');
                    DB::table('notification_digest_members')->where('notification_delivery_id', $deliveryId)->delete();

                    continue;
                }
                if ($resolved->digestCadence === DigestCadence::Immediate) {
                    $delivery->forceFill([
                        'digest_cadence' => DigestCadence::Immediate,
                        'due_at' => $resolved->dueAt,
                        'routing_reason' => $resolved->reason,
                    ])->save();
                    DB::table('notification_digest_members')->where('notification_delivery_id', $deliveryId)->delete();

                    continue;
                }
                if ($resolved->dueAt->isAfter($now)) {
                    $delivery->forceFill([
                        'digest_cadence' => $resolved->digestCadence,
                        'due_at' => $resolved->dueAt,
                        'routing_reason' => $resolved->reason,
                    ])->save();
                    DB::table('notification_digest_members')->where('notification_delivery_id', $deliveryId)->delete();
                    if ($resolved->dueAt->greaterThan($latestDue)) {
                        $latestDue = $resolved->dueAt;
                    }

                    continue;
                }

                $active[] = [$delivery, $message];
            }

            if ($active === []) {
                $dispatch->forceFill([
                    'status' => DeliveryStatus::Cancelled,
                    'last_error' => 'Digest has no routes currently eligible for delivery.',
                ])->save();

                return null;
            }

            $attemptCount = $dispatch->attempt_count + 1;
            $dispatch->forceFill([
                'status' => DeliveryStatus::Pending,
                'attempt_count' => $attemptCount,
                'next_attempt_at' => null,
                'last_error' => null,
            ])->save();

            $lines = [];
            $deliveryIds = [];
            foreach ($active as [$delivery, $message]) {
                $deliveryIds[] = (string) $delivery->id;
                $summary = trim((string) $message->body);
                $lines[] = '• '.(string) $message->title.($summary !== '' ? ': '.mb_substr($summary, 0, 320) : '');
            }

            return new DeliveryAttempt(
                deliveryId: (string) $dispatch->id,
                messageId: (string) $dispatch->id,
                recipientUserId: (int) $dispatch->recipient_user_id,
                playerId: $dispatch->player_id,
                channel: $dispatch->channel,
                endpointId: $dispatch->notification_endpoint_id,
                attemptCount: $attemptCount,
                maxAttempts: (int) $dispatch->max_attempts,
                notificationType: 'communications.digest',
                messageTitle: 'Kingshot Alliance digest',
                messageBody: implode("\n", $lines),
                messageActionUrl: '/notifications',
                metadata: ['digest_delivery_ids' => $deliveryIds],
            );
        });
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
            $dispatch = NotificationDigestDispatch::query()
                ->whereKey($attempt->deliveryId)
                ->where('status', DeliveryStatus::Pending->value)
                ->where('attempt_count', $attempt->attemptCount)
                ->lockForUpdate()
                ->first();
            if (! $dispatch instanceof NotificationDigestDispatch) {
                return;
            }

            $deliveryIds = is_array($attempt->metadata['digest_delivery_ids'] ?? null)
                ? array_values(array_filter($attempt->metadata['digest_delivery_ids'], 'is_string'))
                : [];
            $retryable = ! $outcome->delivered
                && $outcome->retryable
                && $attempt->attemptCount < $attempt->maxAttempts;

            $dispatch->forceFill($outcome->delivered ? [
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

            if ($outcome->delivered || ! $retryable) {
                foreach ($deliveryIds as $deliveryId) {
                    $delivery = NotificationDelivery::query()->whereKey($deliveryId)
                        ->whereExists(static function ($members) use ($attempt): void {
                            $members->selectRaw('1')->from('notification_digest_members')
                                ->whereColumn('notification_delivery_id', 'notification_deliveries.id')
                                ->where('notification_digest_dispatch_id', $attempt->deliveryId);
                        })->lockForUpdate()->first();
                    if (! $delivery instanceof NotificationDelivery || $delivery->status !== DeliveryStatus::Queued) {
                        continue;
                    }
                    $delivery->forceFill($outcome->delivered ? [
                        'status' => DeliveryStatus::Sent,
                        'sent_at' => now(),
                        'failed_at' => null,
                        'next_attempt_at' => null,
                        'attempt_count' => max((int) $delivery->attempt_count, $attempt->attemptCount),
                        'last_error' => null,
                    ] : [
                        'status' => DeliveryStatus::Failed,
                        'failed_at' => now(),
                        'next_attempt_at' => null,
                        'attempt_count' => max((int) $delivery->attempt_count, $attempt->attemptCount),
                        'last_error' => mb_substr((string) $outcome->error, 0, 2000),
                    ])->save();
                    $this->recordBroadcastOutcome($delivery, $outcome, false, $attempt->attemptCount);
                }
            }
            $this->health->record($endpoint, $outcome);
        });
    }

    /** Expired attempts without remaining budget are reconciled once, never resent. */
    private function exhausted(NotificationDigestDispatch $dispatch): void
    {
        $outcome = DeliveryOutcome::failed(NotificationAttemptEligibility::EXHAUSTED_REASON, false);
        $dispatch->forceFill([
            'status' => DeliveryStatus::Failed,
            'failed_at' => now(),
            'next_attempt_at' => null,
            'last_error' => $outcome->error,
        ])->save();
        $ids = DB::table('notification_digest_members')
            ->where('notification_digest_dispatch_id', $dispatch->id)
            ->orderBy('notification_delivery_id')->limit(self::MAX_MEMBERS)
            ->pluck('notification_delivery_id');
        foreach ($ids as $id) {
            $delivery = NotificationDelivery::query()->whereKey($id)->lockForUpdate()->first();
            if (! $delivery instanceof NotificationDelivery || $delivery->status !== DeliveryStatus::Queued) {
                continue;
            }
            $delivery->forceFill([
                'status' => DeliveryStatus::Failed,
                'failed_at' => now(),
                'next_attempt_at' => null,
                'attempt_count' => max((int) $delivery->attempt_count, (int) $dispatch->attempt_count),
                'last_error' => $outcome->error,
            ])->save();
            $this->recordBroadcastOutcome($delivery, $outcome, false, (int) $dispatch->attempt_count, exhausted: true);
        }
    }

    private function recordBroadcastOutcome(
        NotificationDelivery $delivery,
        DeliveryOutcome $outcome,
        bool $retryable,
        int $attemptCount,
        bool $exhausted = false,
    ): void {
        $message = NotificationMessage::query()->whereKey($delivery->notification_message_id)->first();
        $metadata = $message instanceof NotificationMessage && is_array($message->metadata) ? $message->metadata : [];
        $allianceId = $metadata['alliance_id'] ?? null;
        $runId = $metadata['broadcast_run_id'] ?? null;
        $contentItemId = $metadata['content_item_id'] ?? null;
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
                'channel' => $delivery->channel->value,
                'status' => $delivery->status->value,
                'attempt_count' => $attemptCount,
                'retryable' => $retryable,
            ],
            'broadcast-delivery:'.$delivery->id.':digest-attempt:'.$attemptCount.($exhausted ? ':exhausted' : ''),
            'alliance:'.$allianceId,
        );
    }

    private function cancelDelivery(NotificationDelivery $delivery, string $reason): void
    {
        $delivery->forceFill([
            'status' => DeliveryStatus::Cancelled,
            'next_attempt_at' => null,
            'last_error' => mb_substr($reason, 0, 2000),
        ])->save();
    }
}
