<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Queries;

use App\Contexts\Operations\Access\Enums\OperationsPermission;

/** Operations declares its permission meaning without importing another owner's role vocabulary. */
final class KingdomOperationsRolePolicy
{
    /** @return list<OperationsPermission> */
    public function management(): array
    {
        return [OperationsPermission::EventKingdomView, OperationsPermission::EventKingdomCreate,
            OperationsPermission::EventKingdomManage, OperationsPermission::TerritoryKingdomView,
            OperationsPermission::TerritoryKingdomManage];
    }

    /** @return list<OperationsPermission> */
    public function viewing(): array
    {
        return [OperationsPermission::EventKingdomView, OperationsPermission::TerritoryKingdomView];
    }
}
