<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Access\Enums;

enum TransferPermission: string
{
    case Manage = 'kingdom_transfer.manage';
}
