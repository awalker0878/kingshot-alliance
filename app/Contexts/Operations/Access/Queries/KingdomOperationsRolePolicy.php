<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Queries;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\Operations\Access\Enums\OperationsPermission;

/** Operations declares the permission meaning; consumers may compare immutable facts. */
final class KingdomOperationsRolePolicy
{
    /** @return list<OperationsPermission> */
    public function permissions(DefaultKingdomRole $role): array
    {
        return match ($role) {
            DefaultKingdomRole::Administrator, DefaultKingdomRole::EventCoordinator => [
                OperationsPermission::EventKingdomView,
                OperationsPermission::EventKingdomCreate,
                OperationsPermission::EventKingdomManage,
                OperationsPermission::TerritoryKingdomView,
                OperationsPermission::TerritoryKingdomManage,
            ],
            DefaultKingdomRole::Viewer => [OperationsPermission::EventKingdomView, OperationsPermission::TerritoryKingdomView],
        };
    }
}
