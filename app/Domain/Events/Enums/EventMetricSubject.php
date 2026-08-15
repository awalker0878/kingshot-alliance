<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventMetricSubject: string
{
    case Event = 'event';
    case KingdomAlliance = 'kingdom_alliance';
    case Player = 'player';
}
