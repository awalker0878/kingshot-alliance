<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Enums;

enum EventScope: string
{
    case Player = 'player';
    case Alliance = 'alliance';
    case Kingdom = 'kingdom';
}
