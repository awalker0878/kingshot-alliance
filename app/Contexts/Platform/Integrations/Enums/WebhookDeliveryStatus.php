<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Enums;

enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
