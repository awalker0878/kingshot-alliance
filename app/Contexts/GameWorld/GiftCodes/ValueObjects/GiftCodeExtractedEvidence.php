<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeExtractedEvidence
{
    /**
     * @param  array<string, mixed>|null  $applicability
     * @param  array<string, mixed>|null  $reward
     */
    public function __construct(
        public string $code,
        public ?string $claimedExpiresAt,
        public ?string $expiryPrecision,
        public ?string $expiryTimezone,
        public ?array $applicability,
        public ?array $reward,
    ) {}
}
