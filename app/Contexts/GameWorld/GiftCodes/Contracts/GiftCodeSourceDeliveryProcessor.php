<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Contracts;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceDelivery;

interface GiftCodeSourceDeliveryProcessor
{
    public function process(GiftCodeSourceDelivery $delivery): void;
}
