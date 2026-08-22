<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Enums;

enum TerritoryPlanStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
