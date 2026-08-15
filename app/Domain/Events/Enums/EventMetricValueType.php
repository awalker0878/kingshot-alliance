<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventMetricValueType: string
{
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Duration = 'duration';
    case Percentage = 'percentage';
}
