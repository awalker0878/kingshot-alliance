<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Enums;

enum SpatialObservedIdentityState: string
{
    case ResolvedPlayer = 'resolved_player';
    case ResolvedPlanLocal = 'resolved_plan_local';
    case Ambiguous = 'ambiguous';
    case Unresolved = 'unresolved';
}
