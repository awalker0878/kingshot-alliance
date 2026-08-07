<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Authorization\DefaultAllianceRole;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\Permission;
use App\Models\Role;

final class AllianceRoleProvisioner
{
    /** @return array<string, Role> */
    public function provision(Alliance $alliance): array
    {
        $permissions = [];

        foreach (PermissionKey::cases() as $permissionKey) {
            $permissions[$permissionKey->value] = Permission::query()->updateOrCreate(
                ['key' => $permissionKey->value],
                ['description' => $permissionKey->description()],
            );
        }

        $roles = [];

        foreach (DefaultAllianceRole::cases() as $roleTemplate) {
            $role = Role::query()->create([
                'alliance_id' => $alliance->id,
                'key' => $roleTemplate->value,
                'name' => $roleTemplate->name(),
                'is_system' => true,
            ]);

            $role->permissions()->sync(array_map(
                static fn (PermissionKey $permission): string => $permissions[$permission->value]->id,
                $roleTemplate->permissions(),
            ));

            $roles[$roleTemplate->value] = $role;
        }

        return $roles;
    }
}
