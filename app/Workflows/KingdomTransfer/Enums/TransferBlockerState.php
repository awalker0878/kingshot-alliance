<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Enums;

enum TransferBlockerState: string
{
    case Active = 'active';
    case Resolved = 'resolved';
}
