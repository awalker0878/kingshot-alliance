<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Enums;

enum EventMetricSubject: string
{
    case Event = 'event';
    case Alliance = 'alliance';
    case Player = 'player';
}
