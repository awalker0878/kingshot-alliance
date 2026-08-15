<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Shared\Access\Models\Permission as PermissionModel;
use Illuminate\Support\Str;
use RuntimeException;

final class AllianceRoleProvisioner
{
    /** @return array<string, Role> */
    public function provision(Alliance $alliance): array
    {
        $permissionRows = [];
        foreach (DefaultAllianceRole::cases() as $roleTemplate) {
            foreach ($roleTemplate->permissions() as $permission) {
                $permissionRows[$permission->key()] = [
                    'id' => (string) Str::ulid(),
                    'key' => $permission->key(),
                    'description' => $permission->key(),
                ];
            }
        }

        PermissionModel::query()->upsert(array_values($permissionRows), ['key'], ['description']);

        /** @var array<string, PermissionModel> $permissions */
        $permissions = [];
        foreach (PermissionModel::query()->whereIn('key', array_keys($permissionRows))->get() as $permission) {
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
            foreach ($roleTemplate->permissions() as $permission) {
                $permissionRow = $permissions[$permission->key()] ?? null;
                if (! $permissionRow instanceof PermissionModel) {
                    throw new RuntimeException('A default Alliance permission was not provisioned.');
                }
                $permissionIds[] = $permissionRow->id;
            }
            $role->permissions()->sync($permissionIds);
            $roles[$roleTemplate->value] = $role;
        }

        return $roles;
    }
}
