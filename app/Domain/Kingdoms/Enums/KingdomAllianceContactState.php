<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum KingdomAllianceContactState: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
