<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Enums;

enum EventRosterType: string
{
    case Roster = 'roster';
    case Combatants = 'combatants';
    case Substitutes = 'substitutes';
    case Team = 'team';
    case Legion = 'legion';
}
