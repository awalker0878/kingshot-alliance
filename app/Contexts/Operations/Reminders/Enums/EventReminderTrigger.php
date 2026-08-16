<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Reminders\Enums;

enum EventReminderTrigger: string
{
    case BeforeStart = 'before_start';
    case BeforePollClose = 'before_poll_close';
}
