<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Contracts;

use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeReference;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

interface GiftCodeRedemptionProvider
{
    public function name(): string;

    public function begin(GiftCodeReference $giftCode, PlayerReference $player): GiftCodeRedemptionOutcome;
}
