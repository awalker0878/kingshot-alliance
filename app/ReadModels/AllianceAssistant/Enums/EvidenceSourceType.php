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
    case AllianceCommand = 'alliance_command';
    case EventReadiness = 'event_readiness';
    case BearHuntRun = 'bear_hunt_run';
    case RosterFreshness = 'roster_freshness';
    case TransferVerification = 'transfer_verification';
    case TerritoryComparison = 'territory_comparison';
    case AllianceSettings = 'alliance_settings';
    case AllianceGovernanceHistory = 'alliance_governance_history';
    case AllianceRosterReconciliation = 'alliance_roster_reconciliation';
}
