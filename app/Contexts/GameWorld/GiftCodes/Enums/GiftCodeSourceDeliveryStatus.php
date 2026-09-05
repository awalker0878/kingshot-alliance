<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeSourceDeliveryStatus: string
{
    case Received = 'received';
    case Authenticated = 'authenticated';
    case Queued = 'queued';
    case Processing = 'processing';
    case Processed = 'processed';
    case Duplicate = 'duplicate';
    case Quarantined = 'quarantined';
    case Failed = 'failed';
}
