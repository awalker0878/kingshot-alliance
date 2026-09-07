<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferCapacityBucket: string
{
    case OrdinaryInvite = 'ordinary_invite';
    case TransferOpen = 'transfer_open';
}
