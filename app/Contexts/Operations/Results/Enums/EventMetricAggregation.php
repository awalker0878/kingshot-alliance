<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Enums;

enum EventMetricAggregation: string
{
    case Sum = 'sum';
    case Max = 'max';
    case Min = 'min';
    case Average = 'average';
    case Latest = 'latest';
}
