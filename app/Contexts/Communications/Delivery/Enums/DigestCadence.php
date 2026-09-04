<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Enums;

enum DigestCadence: string
{
    case Immediate = 'immediate';
    case Hourly = 'hourly';
    case Daily = 'daily';
}
