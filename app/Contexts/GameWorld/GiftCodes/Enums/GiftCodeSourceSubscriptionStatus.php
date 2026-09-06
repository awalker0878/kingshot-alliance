<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeSourceSubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Degraded = 'degraded';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Disabled = 'disabled';
}
