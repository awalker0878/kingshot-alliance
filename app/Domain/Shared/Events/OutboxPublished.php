<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

final readonly class OutboxPublished
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $messageId,
        public ?string $allianceId,
        public string $eventType,
        public string $aggregateType,
        public string $aggregateId,
        public string $idempotencyKey,
        public array $payload,
        public string $occurredAt,
    ) {}
}
