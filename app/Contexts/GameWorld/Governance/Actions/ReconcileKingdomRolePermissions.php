<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Shared\Infrastructure\Access\Models\Permission;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class ReconcileKingdomRolePermissions
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string, list<string>> $permissionKeysByRoleId */
    public function handle(string $kingdomId, string $permissionOwnerKey, array $permissionKeysByRoleId): void
    {
        if ($permissionKeysByRoleId === []) {
            return;
        }
        if (trim($permissionOwnerKey) === '') {
            throw new RuntimeException('Permission owner key is required for exact reconciliation.');
        }

        DB::transaction(function () use ($kingdomId, $permissionOwnerKey, $permissionKeysByRoleId): void {
            $roles = KingdomRole::query()
                ->where('kingdom_id', $kingdomId)
                ->whereIn('id', array_keys($permissionKeysByRoleId))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($roles->count() !== count($permissionKeysByRoleId)) {
                throw new RuntimeException('A requested GameWorld Kingdom role is not provisioned in this Kingdom.');
            }

            $allKeys = array_values(array_unique(array_map('strval', array_merge(...array_values($permissionKeysByRoleId)))));
            $permissions = Permission::query()->whereIn('key', $allKeys)->get()->keyBy('key');
            foreach ($allKeys as $key) {
                $permission = $permissions->get($key);
                if (! $permission instanceof Permission) {
                    throw new RuntimeException("Requested permission [{$key}] is not provisioned.");
                }
                if ((string) $permission->owner_key !== $permissionOwnerKey) {
                    throw new RuntimeException("Requested permission [{$key}] is not owned by [{$permissionOwnerKey}].");
                }
            }

            foreach ($permissionKeysByRoleId as $roleId => $keys) {
                $role = $roles->get($roleId);
                if (! $role instanceof KingdomRole) {
                    throw new RuntimeException('The requested GameWorld Kingdom role is not provisioned in this Kingdom.');
                }

                $desiredIds = [];
                foreach (array_values(array_unique($keys)) as $key) {
                    $permission = $permissions->get($key);
                    if (! $permission instanceof Permission) {
                        throw new RuntimeException("Requested permission [{$key}] is not provisioned.");
                    }
                    $desiredIds[] = (string) $permission->id;
                }
                $currentOwnedIds = $role->permissions()
                    ->where('permissions.owner_key', $permissionOwnerKey)
                    ->pluck('permissions.id')
                    ->map('strval')
                    ->values()
                    ->all();

                $detach = array_values(array_diff($currentOwnedIds, $desiredIds));
                $attach = array_values(array_diff($desiredIds, $currentOwnedIds));
                if ($detach === [] && $attach === []) {
                    continue;
                }

                if ($detach !== []) {
                    $role->permissions()->detach($detach);
                }
                if ($attach !== []) {
                    $role->permissions()->attach($attach);
                }

                $metadata = [
                    'kingdom_id' => $kingdomId,
                    'role_id' => (string) $role->id,
                    'role_key' => (string) $role->key,
                    'permission_owner' => $permissionOwnerKey,
                    'added_permission_ids' => $attach,
                    'removed_permission_ids' => $detach,
                ];
                $this->audit->record('kingdom.role_permissions_reconciled', null, $role, null, $metadata);
                $this->outbox->record('kingdom.role_permissions_reconciled', null, $role, $metadata);
            }
        });
    }
}
