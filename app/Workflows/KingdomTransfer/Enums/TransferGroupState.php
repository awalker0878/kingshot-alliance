<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Enums;

enum TransferGroupState: string
{
    case Active = 'active';
    case Archived = 'archived';
}
