<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeSourceRateLimit
{
    public function __construct(
        public ?int $limit = null,
        public ?int $remaining = null,
        public ?int $resetAtUnix = null,
        public ?int $retryAfterSeconds = null,
        public ?int $quotaRemaining = null,
    ) {}

    /** @return array{limit:int|null,remaining:int|null,resetAtUnix:int|null,retryAfterSeconds:int|null,quotaRemaining:int|null} */
    public function toArray(): array
    {
        return [
            'limit' => $this->limit,
            'remaining' => $this->remaining,
            'resetAtUnix' => $this->resetAtUnix,
            'retryAfterSeconds' => $this->retryAfterSeconds,
            'quotaRemaining' => $this->quotaRemaining,
        ];
    }
}
