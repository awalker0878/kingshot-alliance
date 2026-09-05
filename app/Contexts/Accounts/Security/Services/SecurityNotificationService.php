<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Security\Services;

use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use Carbon\CarbonImmutable;

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
        $this->delivery->queue(new NotificationIntent(
            notificationType: 'account.security',
            recipientUserId: $userId,
            playerId: null,
            availableAt: CarbonImmutable::now('UTC'),
            idempotencyKey: $idempotencyKey,
            title: $title,
            body: $body,
            actionUrl: '/profile#security',
            subjectType: 'account_security_event',
            subjectId: mb_substr($event, 0, 64),
            urgency: NotificationUrgency::High,
            metadata: ['event' => $event],
        ));
    }
}
