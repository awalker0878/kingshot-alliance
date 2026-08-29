<?php

declare(strict_types=1);

namespace App\ReadModels\NotificationDelivery\ValueObjects;

final readonly class AllianceNotificationRecipientPage
{
    /** @param list<AllianceNotificationRecipient> $recipients */
    public function __construct(
        public array $recipients,
        public int $examinedCount,
        public ?string $nextCursor,
        public bool $truncated,
    ) {}
}
