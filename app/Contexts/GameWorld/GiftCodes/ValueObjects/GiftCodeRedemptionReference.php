<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use Carbon\CarbonImmutable;

final readonly class GiftCodeRedemptionReference
{
    public function __construct(
        public string $redemptionId,
        public GiftCodeRedemptionStatus $status,
        public int $attempts,
        public ?string $redemptionUrl,
        public ?CarbonImmutable $nextAttemptAt,
        public ?CarbonImmutable $redeemedAt,
    ) {}
}
