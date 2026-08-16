<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
