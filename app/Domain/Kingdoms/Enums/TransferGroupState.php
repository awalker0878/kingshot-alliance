<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum TransferGroupState: string
{
    case Active = 'active';
    case Archived = 'archived';
}
