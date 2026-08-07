<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\Permission;
use App\Domain\Authorization\Models\Role;
use Illuminate\Support\Str;
use RuntimeException;

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

        /** @var array<string, Permission> $permissions */
        $permissions = [];

        foreach (Permission::query()->whereIn('key', array_column($permissionRows, 'key'))->get() as $permission) {
            $permissions[$permission->key] = $permission;
        }

        $roles = [];

        foreach (DefaultAllianceRole::cases() as $roleTemplate) {
            $role = Role::query()->create([
                'alliance_id' => $alliance->id,
                'key' => $roleTemplate->value,
                'name' => $roleTemplate->name(),
                'is_system' => true,
            ]);

            $permissionIds = [];

            foreach ($roleTemplate->permissions() as $permissionKey) {
                $permission = $permissions[$permissionKey->value] ?? null;

                if (! $permission instanceof Permission) {
                    throw new RuntimeException('A default alliance permission was not provisioned.');
                }

                $permissionIds[] = $permission->id;
            }

            $role->permissions()->sync($permissionIds);
            $roles[$roleTemplate->value] = $role;
        }

        return $roles;
    }
}
