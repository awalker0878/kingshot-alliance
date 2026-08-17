<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Enums;

enum KingdomAllianceStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
