<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Enums;

enum TransferReadinessState: string
{
    case NotStarted = 'not_started';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Blocked = 'blocked';
    case Confirmed = 'confirmed';
    case Withdrawn = 'withdrawn';
}
