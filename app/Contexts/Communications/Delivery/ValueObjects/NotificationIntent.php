<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class NotificationIntent
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $notificationType,
        public int $recipientUserId,
        public ?string $playerId,
        public CarbonImmutable $availableAt,
        public string $idempotencyKey,
        public string $title,
        public ?string $body = null,
        public ?string $actionUrl = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public NotificationUrgency $urgency = NotificationUrgency::Normal,
        public array $metadata = [],
        public int $maxAttempts = 5,
    ) {}

    /** @param array<string,mixed> $metadata */
    public static function fromScalars(
        string $notificationType,
        int $recipientUserId,
        ?string $playerId,
        DateTimeInterface $availableAt,
        string $idempotencyKey,
        string $title,
        ?string $body = null,
        ?string $actionUrl = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        NotificationUrgency $urgency = NotificationUrgency::Normal,
        array $metadata = [],
        int $maxAttempts = 5,
    ): self {
        return new self(
            notificationType: $notificationType,
            recipientUserId: $recipientUserId,
            playerId: $playerId,
            availableAt: CarbonImmutable::instance($availableAt),
            idempotencyKey: $idempotencyKey,
            title: $title,
            body: $body,
            actionUrl: $actionUrl,
            subjectType: $subjectType,
            subjectId: $subjectId,
            urgency: $urgency,
            metadata: $metadata,
            maxAttempts: $maxAttempts,
        );
    }
}
