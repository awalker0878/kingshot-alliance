<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventCapability: string
{
    case Responses = 'responses';
    case Registration = 'registration';
    case Waitlist = 'waitlist';
    case Attendance = 'attendance';
    case Phases = 'phases';
    case Polls = 'polls';
    case Rosters = 'rosters';
    case Substitutes = 'substitutes';
    case Teams = 'teams';
    case Legions = 'legions';
    case RallyGuidance = 'rally_guidance';
    case Formations = 'formations';
    case Objectives = 'objectives';
    case KingPerks = 'king_perks';
    case Scoring = 'scoring';
    case Results = 'results';
}
