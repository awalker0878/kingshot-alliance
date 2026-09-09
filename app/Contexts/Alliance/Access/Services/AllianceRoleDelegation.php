<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;

/** Owner-internal grant policy; callers must also authorize current role management. */
final readonly class AllianceRoleDelegation
{
    public function __construct(private AlliancePermissionEvaluator $permissions) {}

    public function allows(AllianceMembership $actor, Alliance $alliance, Role $role, bool $selfAssignment): bool
    {
        if ((string) $role->alliance_id !== (string) $alliance->id
            || (string) $actor->alliance_id !== (string) $alliance->id || $role->archived_at !== null) {
            return false;
        }

        // Leadership can explicitly commission a provisioned specialist on
        // another membership without acquiring that specialist's data access.
        if ($role->is_system && $actor->rank === AllianceRank::R5 && ! $selfAssignment) {
            return true;
        }

        // This system role carries Operations authority, not an Alliance
        // permission list. An empty list must not authorize an ordinary holder
        // of role-management permission to grant it to themselves or a peer.
        if ($role->is_system && $role->key === DefaultAllianceRole::EventCoordinator->value
            && $actor->rank !== AllianceRank::R5
            && ! $actor->roles()->where('roles.id', $role->id)->exists()) {
            return false;
        }

        foreach ($role->permissions as $permissionModel) {
            $permission = AlliancePermission::tryFrom((string) $permissionModel->key);
            if ($permission === null || ! $this->permissions->allows($actor, $alliance, $permission)) {
                return false;
            }
        }

        return true;
    }
}
