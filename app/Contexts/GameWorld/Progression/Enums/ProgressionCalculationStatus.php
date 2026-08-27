<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Enums;

enum ProgressionCalculationStatus: string
{
    case Calculated = 'calculated';
    case Unavailable = 'unavailable';
    case Conflicting = 'conflicting';
    case Invalid = 'invalid';
}
