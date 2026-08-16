<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Enums;

enum KingdomIntelligenceShareTargetState: string
{
    case Active = 'active';
    case Removed = 'removed';
}
