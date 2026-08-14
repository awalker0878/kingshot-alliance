<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventReminderTrigger: string
{
    case BeforeStart = 'before_start';
    case BeforePollClose = 'before_poll_close';
}
