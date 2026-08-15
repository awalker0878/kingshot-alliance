<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Enums;

enum KingSkillStatus: string
{
    case Planned = 'planned';
    case ScheduledInGame = 'scheduled_in_game';
    case Activated = 'activated';
    case Skipped = 'skipped';
}
