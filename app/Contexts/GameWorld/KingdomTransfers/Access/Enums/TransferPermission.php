<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Access\Enums;

enum TransferPermission: string
{
    case Manage = 'kingdom_transfer.manage';
}
