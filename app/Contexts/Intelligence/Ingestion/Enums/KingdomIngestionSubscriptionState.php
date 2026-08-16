<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Enums;

enum KingdomIngestionSubscriptionState: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Disabled = 'disabled';
}
