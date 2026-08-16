<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Enums;

enum RecurrenceFrequency: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
}
