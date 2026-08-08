<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum RosterState: string
{
    case Active = 'active';
    case Tracked = 'tracked';
    case Left = 'left';
}
