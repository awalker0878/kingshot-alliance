<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Services;

use App\Contexts\Communications\Delivery\Enums\EndpointHealthStatus;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryAttempt;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryOutcome;

/** Endpoint observations are committed only with the current, fenced attempt result. */
final class NotificationEndpointHealth
{
    /** Lock before dispatch/delivery rows, matching endpoint deletion's FK lock order. */
    public function lockForAttempt(DeliveryAttempt $attempt): ?NotificationEndpoint
    {
        if (! $attempt->channel->usesStoredEndpoint() || $attempt->endpointId === null) {
            return null;
        }

        return NotificationEndpoint::query()->whereKey($attempt->endpointId)
            ->where('recipient_user_id', $attempt->recipientUserId)
            ->where('channel', $attempt->channel->value)
            ->lockForUpdate()->first();
    }

    /** The caller holds the endpoint lock and has accepted the attempt's generation. */
    public function record(?NotificationEndpoint $endpoint, DeliveryOutcome $outcome): void
    {
        if (! $endpoint instanceof NotificationEndpoint || ! $endpoint->enabled) {
            return;
        }

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
}
