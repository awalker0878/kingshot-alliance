<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use Carbon\CarbonImmutable;

final readonly class GiftCodeRedemptionOutcome
{
    public function __construct(
        public GiftCodeRedemptionStatus $status,
        public string $resultCode,
        public string $message,
        public ?string $redemptionUrl = null,
        public ?CarbonImmutable $retryAt = null,
    ) {}
}
