<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeRedemptionSessionMode: string
{
    case AllActionable = 'all_actionable';
    case Expiring = 'expiring';
    case RetryReady = 'retry_ready';
    case Selected = 'selected';
}
