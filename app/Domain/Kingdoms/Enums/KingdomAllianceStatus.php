<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum KingdomAllianceStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
