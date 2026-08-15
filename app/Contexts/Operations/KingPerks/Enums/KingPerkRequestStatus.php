<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Enums;

enum KingPerkRequestStatus: string
{
    case Submitted = 'submitted';
    case Scheduled = 'scheduled';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}
