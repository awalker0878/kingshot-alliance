<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventPhaseType: string
{
    case Preparation = 'preparation';
    case Voting = 'voting';
    case Registration = 'registration';
    case Matchmaking = 'matchmaking';
    case RosterLock = 'roster_lock';
    case Battle = 'battle';
    case Results = 'results';
    case Custom = 'custom';
}
