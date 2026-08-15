<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Enums;

enum RosterState: string
{
    case Active = 'active';
    case Tracked = 'tracked';
    case Left = 'left';
}
