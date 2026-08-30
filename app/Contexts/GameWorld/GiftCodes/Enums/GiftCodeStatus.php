<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Disputed = 'disputed';
    case Quarantined = 'quarantined';

    public function redeemable(): bool
    {
        return $this === self::Pending || $this === self::Valid || $this === self::Disputed;
    }
}
