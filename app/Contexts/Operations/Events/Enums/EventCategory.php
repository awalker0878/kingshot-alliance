<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Enums;

enum EventCategory: string
{
    case Personal = 'personal';
    case AllianceActivity = 'alliance_activity';
    case AllianceBattle = 'alliance_battle';
    case KingdomBattle = 'kingdom_battle';
    case Competition = 'competition';
    case Progression = 'progression';
    case Custom = 'custom';
}
