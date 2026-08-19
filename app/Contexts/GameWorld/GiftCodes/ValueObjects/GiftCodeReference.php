<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use Carbon\CarbonImmutable;

final readonly class GiftCodeReference
{
    public function __construct(
        public string $giftCodeId,
        public string $code,
        public GiftCodeStatus $status,
        public ?CarbonImmutable $expiresAt,
    ) {}
}
