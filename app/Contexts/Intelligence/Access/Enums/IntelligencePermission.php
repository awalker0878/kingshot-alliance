<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Access\Enums;

use App\Shared\Infrastructure\Access\Contracts\Permission;

enum IntelligencePermission: string implements Permission
{
    case View = 'intelligence.view';
    case ContributionManage = 'contributions.manage';
    case KingdomManage = 'kingdoms.manage';

    public function key(): string
    {
        return $this->value;
    }

    public function description(): string
    {
        return match ($this) {
            self::View => 'View Intelligence capabilities for the active Player Alliance context.',
            self::ContributionManage => 'Manage alliance contribution records, reporting, exports, and report schedules.',
            self::KingdomManage => 'Manage observed Kingdom, roster, transfer-planning, and intelligence state owned by Intelligence.',
        };
    }
}
