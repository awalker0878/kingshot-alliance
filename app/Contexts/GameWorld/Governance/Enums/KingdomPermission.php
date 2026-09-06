<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Enums;

use App\Shared\Infrastructure\Access\Contracts\Permission;

enum KingdomPermission: string implements Permission
{
    case RoleManage = 'kingdom.roles.manage';

    public static function ownerKey(): string
    {
        return 'game-world.governance';
    }

    public function key(): string
    {
        return $this->value;
    }

    public function description(): string
    {
        return match ($this) {
            self::RoleManage => 'Manage roles, assignments, delegations and governance policy for a specific Kingdom.',
        };
    }
}
