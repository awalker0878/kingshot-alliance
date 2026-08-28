<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Enums;

enum EventWorkflowDimension: string
{
    case Participation = 'participation';
    case Roster = 'roster';
    case BattleAssignments = 'battle_assignments';
    case Rallies = 'rallies';
    case TerritoryPlan = 'territory_plan';
    case Results = 'results';
    case ScreenshotEvidence = 'screenshot_evidence';
    case Debrief = 'debrief';
    case ReadinessCloseout = 'readiness_closeout';
}
