<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\GameWorld\Governance\Actions\GrantKingdomRolePermissions;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Shared\Infrastructure\Access\Models\Permission;
use Illuminate\Support\Str;

final readonly class KingdomOperationsRoleProvisioner
{
    public function __construct(private GrantKingdomRolePermissions $grantRolePermissions) {}

    public function provision(string $kingdomId, string $administratorRoleId, string $eventCoordinatorRoleId, string $viewerRoleId): void
    {
        $grants = [
            $administratorRoleId => [
                OperationsPermission::EventKingdomView,
                OperationsPermission::EventKingdomCreate,
                OperationsPermission::EventKingdomManage,
                OperationsPermission::TerritoryKingdomView,
                OperationsPermission::TerritoryKingdomManage,
            ],
            $eventCoordinatorRoleId => [
                OperationsPermission::EventKingdomView,
                OperationsPermission::EventKingdomCreate,
                OperationsPermission::EventKingdomManage,
                OperationsPermission::TerritoryKingdomView,
                OperationsPermission::TerritoryKingdomManage,
            ],
            $viewerRoleId => [OperationsPermission::EventKingdomView, OperationsPermission::TerritoryKingdomView],
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

        $permissionKeysByRoleId = [];
        foreach ($grants as $roleId => $permissions) {
            $permissionKeysByRoleId[$roleId] = array_map(static fn (OperationsPermission $permission): string => $permission->key(), $permissions);
        }

        $this->grantRolePermissions->handle($kingdomId, $permissionKeysByRoleId);
    }
}
