<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum TransferBlockerState: string
{
    case Active = 'active';
    case Resolved = 'resolved';
}
