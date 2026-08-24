<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Enums;

enum AssistantPrompt: string
{
    case SwordlandRoster = 'swordland_roster';
    case NextEvent = 'next_event';
    case BearHuntGuide = 'bear_hunt_guide';
}
