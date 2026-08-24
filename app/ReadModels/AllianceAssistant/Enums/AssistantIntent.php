<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Enums;

enum AssistantIntent: string
{
    case Help = 'help';
    case EventTime = 'event_time';
    case EventRosterSelf = 'event_roster_self';
    case AllianceContent = 'alliance_content';
    case AllianceObservation = 'alliance_observation';
    case Unsupported = 'unsupported';
}
