<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferBlockerState: string
{
    case Active = 'active';
    case Resolved = 'resolved';
}
