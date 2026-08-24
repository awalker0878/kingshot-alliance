<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Enums;

enum AssistantPrompt: string
{
    case SwordlandRoster = 'swordland_roster';
    case NextEvent = 'next_event';
    case BearHuntGuide = 'bear_hunt_guide';
    case Observation = 'observation';
    case HeroFact = 'hero_fact';
    case RsvpWeek = 'rsvp_week';
    case BattleAssignment = 'battle_assignment';
    case TransferStatus = 'transfer_status';
    case TerritoryPlan = 'territory_plan';
}
