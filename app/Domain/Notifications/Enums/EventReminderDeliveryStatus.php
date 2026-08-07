<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

enum EventReminderDeliveryStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
}
