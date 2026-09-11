<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

/** Immutable source identity, not a cached permission grant or transport destination. */
final readonly class NotificationSource
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $notificationType,
        public int $recipientUserId,
        public ?string $playerId,
        public ?string $subjectType,
        public ?string $subjectId,
        public array $metadata,
    ) {}

    public function metadataString(string $key): ?string
    {
        $value = $this->metadata[$key] ?? null;

        return is_string($value) && $value !== '' && strlen($value) <= 128 ? $value : null;
    }
}
