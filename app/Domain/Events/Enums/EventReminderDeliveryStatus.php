<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventReminderDeliveryStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
