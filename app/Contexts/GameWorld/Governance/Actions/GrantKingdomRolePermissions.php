<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Shared\Infrastructure\Access\Models\Permission;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class GrantKingdomRolePermissions
{
    /**
     * @param  array<string, list<string>>  $permissionKeysByRoleId
     */
    public function handle(string $kingdomId, array $permissionKeysByRoleId): void
    {
        if ($permissionKeysByRoleId === []) {
            return;
        }

        DB::transaction(function () use ($kingdomId, $permissionKeysByRoleId): void {
            $roleIds = array_keys($permissionKeysByRoleId);
            $roles = KingdomRole::query()
                ->where('kingdom_id', $kingdomId)
                ->whereIn('id', $roleIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $permissionKeys = [];
            foreach ($permissionKeysByRoleId as $keys) {
                foreach ($keys as $key) {
                    $permissionKeys[$key] = true;
                }
            }

            $permissionIdsByKey = Permission::query()
                ->whereIn('key', array_keys($permissionKeys))
                ->pluck('id', 'key');

            foreach ($permissionKeysByRoleId as $roleId => $keys) {
                $role = $roles->get($roleId);
                if (! $role instanceof KingdomRole) {
                    throw new RuntimeException('The requested GameWorld Kingdom role is not provisioned in this Kingdom.');
                }

                $permissionIds = [];
                foreach ($keys as $key) {
                    $permissionId = $permissionIdsByKey->get($key);
                    if (! is_string($permissionId)) {
                        throw new RuntimeException('A requested permission is not provisioned.');
                    }
                    $permissionIds[] = $permissionId;
                }

                $role->permissions()->syncWithoutDetaching($permissionIds);
            }
        });
    }
}
