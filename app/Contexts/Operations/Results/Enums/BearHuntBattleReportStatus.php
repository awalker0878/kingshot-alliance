<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Enums;

enum BearHuntBattleReportStatus: string
{
    case Accepted = 'accepted';
    case Removed = 'removed';
}
