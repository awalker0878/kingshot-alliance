<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Enums;

enum EvidenceSourceType: string
{
    case Event = 'event';
    case Roster = 'roster';
    case Participation = 'participation';
    case BattlePlanAssignment = 'battle_plan_assignment';
    case TransferAssessment = 'transfer_assessment';
    case TerritoryPlanRevision = 'territory_plan_revision';
    case AllianceContent = 'alliance_content';
    case Observation = 'observation';
    case GameFact = 'game_fact';
}
