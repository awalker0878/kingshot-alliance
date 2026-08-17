<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferReadinessState: string
{
    case NotStarted = 'not_started';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Blocked = 'blocked';
    case Confirmed = 'confirmed';
    case Withdrawn = 'withdrawn';
}
