<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Reminders\Enums;

enum EventReminderDeliveryStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
