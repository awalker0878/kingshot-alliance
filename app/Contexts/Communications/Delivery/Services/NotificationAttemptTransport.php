<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Services;

use App\Contexts\Accounts\Identity\Queries\VerifiedNotificationEmailQuery;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\ValueObjects\AttemptTransportResult;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryAttempt;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryOutcome;

/** Shared immediate/digest provider handoff, after the claim transaction has committed. */
final readonly class NotificationAttemptTransport
{
    public function __construct(
        private ExternalDeliveryChannelRegistry $channels,
        private VerifiedNotificationEmailQuery $email,
    ) {}

    public function deliver(DeliveryAttempt $attempt): AttemptTransportResult
    {
        $adapter = $this->channels->for($attempt->channel);
        if ($adapter === null) {
            return new AttemptTransportResult(DeliveryOutcome::failed('Delivery channel is not supported.', false), null);
        }
        if ($attempt->channel === DeliveryChannel::Email) {
            $address = $this->email->forUser($attempt->recipientUserId);

            return new AttemptTransportResult($address === null
                ? DeliveryOutcome::failed('A verified account email is required for delivery.', false)
                : $adapter->deliver($attempt, ['email' => $address]), null);
        }
        $endpoint = NotificationEndpoint::query()->whereKey($attempt->endpointId)
            ->where('recipient_user_id', $attempt->recipientUserId)
            ->where('channel', $attempt->channel->value)->where('enabled', true)->first();
        if (! $endpoint instanceof NotificationEndpoint || $endpoint->verification_generation < 1) {
            return new AttemptTransportResult(DeliveryOutcome::failed('Notification endpoint is missing or disabled.', false), null);
        }

        // Both values come from the same selected row; never reload the generation after IO.
        $generation = $endpoint->verification_generation;
        $configuration = is_array($endpoint->configuration) ? $endpoint->configuration : [];
        $outcome = $adapter->deliver($attempt, $configuration);

        return new AttemptTransportResult($outcome, $generation);
    }
}
