<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Enums;

enum ContributionPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Season = 'season';
    case Custom = 'custom';
}
