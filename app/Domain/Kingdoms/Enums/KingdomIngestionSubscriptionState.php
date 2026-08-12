<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum KingdomIngestionSubscriptionState: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Disabled = 'disabled';
}
