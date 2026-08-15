<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Shared\Access\Models\Permission;
use Illuminate\Support\Str;
use RuntimeException;

final class KingdomOperationsRoleProvisioner
{
    public function provision(Kingdom $kingdom): void
    {
        $grants = [
            DefaultKingdomRole::Administrator->value => [
                OperationsPermission::EventKingdomView,
                OperationsPermission::EventKingdomCreate,
                OperationsPermission::EventKingdomManage,
            ],
            DefaultKingdomRole::EventCoordinator->value => [
                OperationsPermission::EventKingdomView,
                OperationsPermission::EventKingdomCreate,
                OperationsPermission::EventKingdomManage,
            ],
            DefaultKingdomRole::Viewer->value => [
                OperationsPermission::EventKingdomView,
            ],
        ];

        $requiredPermissions = [];
        foreach ($grants as $permissions) {
            foreach ($permissions as $permission) {
                $requiredPermissions[$permission->key()] = $permission;
            }
        }

        $permissionRows = array_map(
            static fn (OperationsPermission $permission): array => [
                'id' => (string) Str::ulid(),
                'key' => $permission->key(),
                'description' => $permission->description(),
            ],
            array_values($requiredPermissions),
        );
        Permission::query()->upsert($permissionRows, ['key'], ['description']);

        $permissionIdsByKey = Permission::query()
            ->whereIn('key', array_keys($requiredPermissions))
            ->pluck('id', 'key');

        foreach ($grants as $roleKey => $permissions) {
            $role = KingdomRole::query()
                ->where('kingdom_id', $kingdom->id)
                ->where('key', $roleKey)
                ->first();
            if (! $role instanceof KingdomRole) {
                throw new RuntimeException('GameWorld Kingdom roles must be provisioned before Operations grants.');
            }

            $permissionIds = [];
            foreach ($permissions as $permission) {
                $permissionId = $permissionIdsByKey->get($permission->key());
                if (! is_string($permissionId)) {
                    throw new RuntimeException('An Operations Kingdom permission was not provisioned.');
                }
                $permissionIds[] = $permissionId;
            }

            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
