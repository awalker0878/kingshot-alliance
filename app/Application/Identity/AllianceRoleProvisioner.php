<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Authorization\DefaultAllianceRole;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Str;

final class AllianceRoleProvisioner
{
    /** @return array<string, Role> */
    public function provision(Alliance $alliance): array
    {
        $permissionRows = array_map(
            static fn (PermissionKey $permission): array => [
                'id' => (string) Str::ulid(),
                'key' => $permission->value,
                'description' => $permission->description(),
            ],
            PermissionKey::cases(),
        );

        Permission::query()->upsert(
            $permissionRows,
            ['key'],
            ['description'],
        );

        $permissions = Permission::query()
            ->whereIn('key', array_column($permissionRows, 'key'))
            ->get()
            ->keyBy('key');

        $roles = [];

        foreach (DefaultAllianceRole::cases() as $roleTemplate) {
            $role = Role::query()->create([
                'alliance_id' => $alliance->id,
                'key' => $roleTemplate->value,
                'name' => $roleTemplate->name(),
                'is_system' => true,
            ]);

            $role->permissions()->sync(array_map(
                static fn (PermissionKey $permission): string => (string) $permissions->getOrFail($permission->value)->id,
                $roleTemplate->permissions(),
            ));

            $roles[$roleTemplate->value] = $role;
        }

        return $roles;
    }
}
