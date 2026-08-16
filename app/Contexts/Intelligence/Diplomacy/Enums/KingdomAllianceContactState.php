<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Enums;

enum KingdomAllianceContactState: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
