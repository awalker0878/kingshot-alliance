<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Enums;

enum AssistantIntent: string
{
    case Help = 'help';
    case EventTime = 'event_time';
    case EventRosterSelf = 'event_roster_self';
    case EventParticipationSelf = 'event_participation_self';
    case BattlePlanSelf = 'battle_plan_self';
    case GameFact = 'game_fact';
    case TransferStatusSelf = 'transfer_status_self';
    case TerritoryPlan = 'territory_plan';
    case AllianceContent = 'alliance_content';
    case AllianceObservation = 'alliance_observation';
    case IntelligenceChanges = 'intelligence_changes';
    case AllianceCommandAttention = 'alliance_command_attention';
    case EventReadiness = 'event_readiness';
    case RallyGaps = 'rally_gaps';
    case BearHuntHistory = 'bear_hunt_history';
    case ProgressionFreshness = 'progression_freshness';
    case TransferVerification = 'transfer_verification';
    case TerritoryComparison = 'territory_comparison';
    case AllianceSettings = 'alliance_settings';
    case AllianceGovernanceHistory = 'alliance_governance_history';
    case AllianceRosterReconciliation = 'alliance_roster_reconciliation';
    case ActionHandoff = 'action_handoff';
    case Unsupported = 'unsupported';
}
