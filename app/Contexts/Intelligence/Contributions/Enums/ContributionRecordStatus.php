<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Enums;

enum ContributionRecordStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Reversed = 'reversed';
}
