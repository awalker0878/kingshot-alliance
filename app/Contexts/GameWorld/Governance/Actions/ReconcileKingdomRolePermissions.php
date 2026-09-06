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

            $allKeys = collect($permissionKeysByRoleId)->flatten()->map('strval')->unique()->values();
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

                $desiredIds = collect($keys)
                    ->map(static fn (string $key): string => (string) $permissions->get($key)->id)
                    ->unique()
                    ->values();
                $currentOwnedIds = $role->permissions()
                    ->where('permissions.owner_key', $permissionOwnerKey)
                    ->pluck('permissions.id')
                    ->map('strval')
                    ->values();

                $detach = $currentOwnedIds->diff($desiredIds)->values()->all();
                $attach = $desiredIds->diff($currentOwnedIds)->values()->all();
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
