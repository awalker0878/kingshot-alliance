<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Enums;

enum EventMetricSource: string
{
    case Manual = 'manual';
    case Derived = 'derived';
    case Imported = 'imported';
}
