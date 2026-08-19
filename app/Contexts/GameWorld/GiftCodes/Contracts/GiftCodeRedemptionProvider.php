<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Contracts;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

interface GiftCodeRedemptionProvider
{
    public function name(): string;

    public function begin(GiftCode $giftCode, PlayerReference $player): GiftCodeRedemptionOutcome;
}
