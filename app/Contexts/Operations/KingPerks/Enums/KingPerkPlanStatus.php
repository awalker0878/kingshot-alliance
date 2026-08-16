<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Enums;

enum KingPerkPlanStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Active = 'active';
    case Closed = 'closed';
}
