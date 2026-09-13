<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\GameWorld\Governance\Actions\ReconcileKingdomRolePermissions;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Queries\KingdomOperationsRolePolicy;
use App\Shared\Infrastructure\Access\Models\Permission;
use Illuminate\Support\Str;

final readonly class KingdomOperationsRoleProvisioner
{
    public function __construct(private ReconcileKingdomRolePermissions $reconcileRolePermissions, private KingdomOperationsRolePolicy $policy) {}

    public function provision(string $kingdomId, string $administratorRoleId, string $eventCoordinatorRoleId, string $viewerRoleId): void
    {
        $grants = [
            $administratorRoleId => $this->policy->management(),
            $eventCoordinatorRoleId => $this->policy->management(),
            $viewerRoleId => $this->policy->viewing(),
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
                'owner_key' => OperationsPermission::ownerKey(),
                'description' => $permission->description(),
            ],
            array_values($requiredPermissions),
        );
        Permission::query()->upsert($permissionRows, ['key'], ['owner_key', 'description']);

        $permissionKeysByRoleId = [];
        foreach ($grants as $roleId => $permissions) {
            $permissionKeysByRoleId[$roleId] = array_map(static fn (OperationsPermission $permission): string => $permission->key(), $permissions);
        }

        $this->reconcileRolePermissions->handle($kingdomId, OperationsPermission::ownerKey(), $permissionKeysByRoleId);
    }
}
