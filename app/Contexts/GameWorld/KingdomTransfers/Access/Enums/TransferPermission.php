<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Access\Enums;

enum TransferPermission: string
{
    case View = 'kingdom_transfer.view';
    case Manage = 'kingdom_transfer.manage';
}
