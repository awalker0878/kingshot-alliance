<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferGroupState: string
{
    case Active = 'active';
    case Archived = 'archived';
}
