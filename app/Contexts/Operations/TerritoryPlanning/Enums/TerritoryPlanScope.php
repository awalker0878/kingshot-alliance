<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Enums;

enum TerritoryPlanScope: string
{
    case Alliance = 'alliance';
    case Kingdom = 'kingdom';
}
