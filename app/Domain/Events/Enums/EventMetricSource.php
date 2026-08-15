<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventMetricSource: string
{
    case Manual = 'manual';
    case Derived = 'derived';
    case Imported = 'imported';
}
