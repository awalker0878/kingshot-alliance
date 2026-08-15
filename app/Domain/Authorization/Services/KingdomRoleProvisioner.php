<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\KingdomRole;
use App\Domain\Authorization\Models\Permission;
use App\Contexts\GameWorld\Models\Kingdom;
use Illuminate\Support\Str;
use RuntimeException;

final class KingdomRoleProvisioner
{
    /** @return array<string, KingdomRole> */
    public function provision(Kingdom $kingdom): array
    {
        $requiredPermissions = [];

        foreach (DefaultKingdomRole::cases() as $roleTemplate) {
            foreach ($roleTemplate->permissions() as $permission) {
                $requiredPermissions[$permission->value] = $permission;
            }
        }

        $permissionRows = array_map(
            static fn (PermissionKey $permission): array => [
                'id' => (string) Str::ulid(),
                'key' => $permission->value,
                'description' => $permission->description(),
            ],
            array_values($requiredPermissions),
        );

        Permission::query()->upsert($permissionRows, ['key'], ['description']);

        /** @var array<string, Permission> $permissions */
        $permissions = [];
        foreach (Permission::query()->whereIn('key', array_keys($requiredPermissions))->get() as $permission) {
            $permissions[$permission->key] = $permission;
        }

        $roles = [];
        foreach (DefaultKingdomRole::cases() as $roleTemplate) {
            $role = KingdomRole::query()->firstOrCreate(
                [
                    'kingdom_id' => $kingdom->id,
                    'key' => $roleTemplate->value,
                ],
                [
                    'name' => $roleTemplate->name(),
                    'is_system' => true,
                ],
            );

            if ($role->name !== $roleTemplate->name() || ! $role->is_system) {
                $role->forceFill([
                    'name' => $roleTemplate->name(),
                    'is_system' => true,
                ])->save();
            }

            $permissionIds = [];
            foreach ($roleTemplate->permissions() as $permissionKey) {
                $permission = $permissions[$permissionKey->value] ?? null;
                if (! $permission instanceof Permission) {
                    throw new RuntimeException('A default kingdom permission was not provisioned.');
                }
                $permissionIds[] = $permission->id;
            }

            $role->permissions()->sync($permissionIds);
            $roles[$roleTemplate->value] = $role;
        }

        return $roles;
    }
}
