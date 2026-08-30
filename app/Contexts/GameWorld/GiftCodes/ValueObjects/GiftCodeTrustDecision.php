<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use Carbon\CarbonImmutable;

final readonly class GiftCodeTrustDecision
{
    /** @param list<string> $evidenceIds */
    public function __construct(
        public GiftCodeStatus $status,
        public string $reasonCode,
        public array $evidenceIds,
        public ?CarbonImmutable $expiresAt = null,
        public ?string $expiresPrecision = null,
    ) {}
}
