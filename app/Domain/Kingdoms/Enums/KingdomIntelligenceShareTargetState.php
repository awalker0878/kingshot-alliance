<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum KingdomIntelligenceShareTargetState: string
{
    case Active = 'active';
    case Removed = 'removed';
}
