<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Security\Services;

use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;

final readonly class SecurityNotificationService
{
    public function __construct(private NotificationDeliveryService $delivery) {}

    public function publish(
        int $userId,
        string $event,
        string $title,
        string $body,
        string $idempotencyKey,
    ): void {
        $this->delivery->queueEnabledChannelBatch(
            notificationType: 'account.security',
            recipientUserId: $userId,
            playerId: null,
            dueAt: now(),
            idempotencyKey: $idempotencyKey,
            metadata: [
                'event' => $event,
                'title' => $title,
                'body' => $body,
                'action_url' => '/profile#security',
            ],
        );
    }
}
