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
     * @param array<string, list<string>> $permissionKeysByRoleKey
     */
    public function handle(string $kingdomId, array $permissionKeysByRoleKey): void
    {
        if ($permissionKeysByRoleKey === []) {
            return;
        }

        DB::transaction(function () use ($kingdomId, $permissionKeysByRoleKey): void {
            $roleKeys = array_keys($permissionKeysByRoleKey);
            $roles = KingdomRole::query()
                ->where('kingdom_id', $kingdomId)
                ->whereIn('key', $roleKeys)
                ->lockForUpdate()
                ->get()
                ->keyBy('key');

            $permissionKeys = [];
            foreach ($permissionKeysByRoleKey as $keys) {
                foreach ($keys as $key) {
                    $permissionKeys[$key] = true;
                }
            }

            $permissionIdsByKey = Permission::query()
                ->whereIn('key', array_keys($permissionKeys))
                ->pluck('id', 'key');

            foreach ($permissionKeysByRoleKey as $roleKey => $keys) {
                $role = $roles->get($roleKey);
                if (! $role instanceof KingdomRole) {
                    throw new RuntimeException('The requested GameWorld Kingdom role is not provisioned.');
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
