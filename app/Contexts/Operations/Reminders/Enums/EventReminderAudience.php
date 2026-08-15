<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Reminders\Enums;

enum EventReminderAudience: string
{
    case Target = 'target';
    case Responded = 'responded';
    case Registered = 'registered';
    case Rostered = 'rostered';
    case AllScopePlayers = 'all_scope_players';
}
