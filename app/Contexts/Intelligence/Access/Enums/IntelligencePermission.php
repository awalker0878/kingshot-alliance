<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Access\Enums;

use App\Shared\Access\Contracts\Permission;

enum IntelligencePermission: string implements Permission
{
    case ContributionManage = 'contributions.manage';
    case KingdomManage = 'kingdoms.manage';

    public function key(): string
    {
        return $this->value;
    }

    public function description(): string
    {
        return match ($this) {
            self::ContributionManage => 'Manage alliance contribution records, reporting, exports, and report schedules.',
            self::KingdomManage => 'Manage observed Kingdom, roster, transfer-planning, and intelligence state owned by Intelligence.',
        };
    }
}
