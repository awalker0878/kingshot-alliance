<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Services;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationQueueReceipt;
use App\Contexts\Communications\Delivery\ValueObjects\ResolvedDeliveryRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class NotificationDeliveryService
{
    public function __construct(private NotificationRouteResolver $routes) {}

    public function queue(NotificationIntent $intent): NotificationQueueReceipt
    {
        $this->validateIntent($intent);

        return DB::transaction(function () use ($intent): NotificationQueueReceipt {
            $message = NotificationMessage::query()->firstOrCreate(
                ['idempotency_key' => hash('sha256', $intent->idempotencyKey)],
                [
                    'notification_type' => $intent->notificationType,
                    'recipient_user_id' => $intent->recipientUserId,
                    'player_id' => $intent->playerId,
                    'subject_type' => $intent->subjectType,
                    'subject_id' => $intent->subjectId,
                    'title' => trim($intent->title),
                    'body' => $intent->body,
                    'action_url' => $intent->actionUrl,
                    'urgency' => $intent->urgency->value,
                    'available_at' => $intent->availableAt,
                    'metadata' => $intent->metadata === [] ? null : $intent->metadata,
                ],
            );

            $createdMessage = $message->wasRecentlyCreated;
            if ($createdMessage) {
                foreach ($this->routes->resolve($intent)->routes as $route) {
                    $this->createRoute($message, $route, $intent->maxAttempts);
                }
            }

            $deliveries = NotificationDelivery::query()
                ->where('notification_message_id', (string) $message->id)
                ->orderBy('created_at')
                ->get();
            $inApp = $deliveries->first(
                static fn (NotificationDelivery $delivery): bool => $delivery->channel === DeliveryChannel::InApp,
            );
            $deliveryIds = array_values($deliveries
                ->map(static fn (NotificationDelivery $delivery): string => (string) $delivery->id)
                ->values()
                ->all());
            $channels = array_values($deliveries
                ->map(static fn (NotificationDelivery $delivery): string => $delivery->channel->value)
                ->unique()
                ->values()
                ->all());

            return new NotificationQueueReceipt(
                messageId: (string) $message->id,
                deliveryIds: $deliveryIds,
                channels: $channels,
                createdDeliveryIds: $createdMessage ? $deliveryIds : [],
                createdMessage: $createdMessage,
                inAppDeliveryId: $inApp instanceof NotificationDelivery ? (string) $inApp->id : null,
            );
        });
    }

    private function createRoute(
        NotificationMessage $message,
        ResolvedDeliveryRoute $route,
        int $maxAttempts,
    ): NotificationDelivery {
        $queuedAt = now();
        $isInApp = $route->channel === DeliveryChannel::InApp;

        return NotificationDelivery::query()->firstOrCreate(
            [
                'idempotency_key' => hash('sha256', implode('|', [
                    (string) $message->id,
                    $route->channel->value,
                    $route->endpointId ?? 'native',
                ])),
            ],
            [
                'notification_message_id' => (string) $message->id,
                'channel' => $route->channel->value,
                'notification_endpoint_id' => $route->endpointId,
                'route_target_label' => $route->targetLabel,
                'digest_cadence' => $route->digestCadence->value,
                'due_at' => $route->dueAt,
                'status' => $isInApp ? DeliveryStatus::Sent->value : DeliveryStatus::Queued->value,
                'attempt_count' => 0,
                'max_attempts' => max(1, min(10, $maxAttempts)),
                'queued_at' => $queuedAt,
                'sent_at' => $isInApp ? $queuedAt : null,
                'routing_reason' => $route->reason,
            ],
        );
    }

    private function validateIntent(NotificationIntent $intent): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9_.-]{0,95}$/', $intent->notificationType)) {
            throw ValidationException::withMessages([
                'notification_type' => 'Notification type is invalid.',
            ]);
        }

        if ($intent->recipientUserId < 1) {
            throw ValidationException::withMessages(['recipient' => 'Notification recipient is invalid.']);
        }

        if ($intent->playerId !== null && ! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $intent->playerId)) {
            throw ValidationException::withMessages(['player' => 'Notification Governor is invalid.']);
        }

        foreach ($intent->eligiblePlayerIds as $playerId) {
            if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $playerId)) {
                throw ValidationException::withMessages(['players' => 'An eligible Governor is invalid.']);
            }
        }

        $title = trim($intent->title);
        if ($title === '' || mb_strlen($title) > 240) {
            throw ValidationException::withMessages(['title' => 'Notification title is invalid.']);
        }

        if ($intent->body !== null && mb_strlen($intent->body) > 12000) {
            throw ValidationException::withMessages(['body' => 'Notification body is too long.']);
        }

        if ($intent->subjectType !== null && mb_strlen($intent->subjectType) > 64) {
            throw ValidationException::withMessages(['subject_type' => 'Notification subject type is too long.']);
        }

        if ($intent->subjectId !== null && mb_strlen($intent->subjectId) > 64) {
            throw ValidationException::withMessages(['subject_id' => 'Notification subject ID is too long.']);
        }

        if ($intent->actionUrl !== null) {
            if (mb_strlen($intent->actionUrl) > 2048
                || ! str_starts_with($intent->actionUrl, '/')
                || str_starts_with($intent->actionUrl, '//')
                || str_contains($intent->actionUrl, "\r")
                || str_contains($intent->actionUrl, "\n")) {
                throw ValidationException::withMessages([
                    'action_url' => 'Notification action URL must be a safe application-relative path.',
                ]);
            }
        }

        if ($intent->maxAttempts < 1 || $intent->maxAttempts > 10) {
            throw ValidationException::withMessages([
                'max_attempts' => 'Notification attempt budget must be between 1 and 10.',
            ]);
        }
    }
}
