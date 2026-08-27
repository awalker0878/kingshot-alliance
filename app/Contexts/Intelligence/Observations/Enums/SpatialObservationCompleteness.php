<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Enums;

enum SpatialObservationCompleteness: string
{
    case Complete = 'complete';
    case Partial = 'partial';
    case Unknown = 'unknown';
}
