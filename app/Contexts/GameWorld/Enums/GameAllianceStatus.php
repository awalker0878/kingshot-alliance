<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Enums;

enum GameAllianceStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
