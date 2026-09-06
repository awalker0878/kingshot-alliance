<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodePushDeliveryReceipt
{
    public function __construct(
        public string $deliveryId,
        public bool $created,
    ) {}
}
