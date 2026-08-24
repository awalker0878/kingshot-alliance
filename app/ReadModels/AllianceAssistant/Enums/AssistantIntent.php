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
    case ActionHandoff = 'action_handoff';
    case Unsupported = 'unsupported';
}
