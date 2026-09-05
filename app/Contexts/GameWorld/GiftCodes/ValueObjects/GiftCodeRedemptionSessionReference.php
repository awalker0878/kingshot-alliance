<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeRedemptionSessionReference
{
    public function __construct(
        public string $sessionId,
        public int $totalItems,
    ) {}
}
