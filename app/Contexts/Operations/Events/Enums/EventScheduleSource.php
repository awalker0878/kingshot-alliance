<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Enums;

enum EventScheduleSource: string
{
    case AllianceControlled = 'alliance_controlled';
    case GameCalendar = 'game_calendar';
    case Matchmaking = 'matchmaking';
    case Manual = 'manual';
}
