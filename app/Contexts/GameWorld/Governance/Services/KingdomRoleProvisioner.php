<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use App\Contexts\GameWorld\Governance\Actions\ReconcileKingdomRolePermissions;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Shared\Infrastructure\Access\Models\Permission;
use RuntimeException;

final readonly class KingdomRoleProvisioner
{
    public function __construct(private ReconcileKingdomRolePermissions $reconcilePermissions) {}

    /** @return array<string, KingdomRole> */
    public function provision(Kingdom $kingdom): array
    {
        $permission = Permission::query()->firstOrCreate(
            ['key' => KingdomPermission::RoleManage->key()],
            ['owner_key' => KingdomPermission::ownerKey(), 'description' => KingdomPermission::RoleManage->description()],
        );
        if ($permission->owner_key !== KingdomPermission::ownerKey() || $permission->description !== KingdomPermission::RoleManage->description()) {
            $permission->forceFill(['owner_key' => KingdomPermission::ownerKey(), 'description' => KingdomPermission::RoleManage->description()])->save();
        }

        $roles = [];
        foreach (DefaultKingdomRole::cases() as $roleTemplate) {
            $role = KingdomRole::query()->firstOrCreate(
                ['kingdom_id' => $kingdom->id, 'key' => $roleTemplate->value],
                ['name' => $roleTemplate->name(), 'description' => $roleTemplate->description(), 'is_system' => true, 'archived_at' => null],
            );
            $desired = ['name' => $roleTemplate->name(), 'description' => $roleTemplate->description(), 'is_system' => true, 'archived_at' => null];
            if ($role->name !== $desired['name'] || $role->description !== $desired['description'] || ! $role->is_system || $role->archived_at !== null) {
                $role->forceFill($desired)->save();
            }
            $roles[$roleTemplate->value] = $role;
        }

        $administrator = $roles[DefaultKingdomRole::Administrator->value] ?? null;
        if (! $administrator instanceof KingdomRole) {
            throw new RuntimeException('The Kingdom Administrator role was not provisioned.');
        }

        $this->reconcilePermissions->handle(
            (string) $kingdom->id,
            KingdomPermission::ownerKey(),
            [
                (string) $roles[DefaultKingdomRole::Administrator->value]->id => [KingdomPermission::RoleManage->key()],
                (string) $roles[DefaultKingdomRole::EventCoordinator->value]->id => [],
                (string) $roles[DefaultKingdomRole::Viewer->value]->id => [],
            ],
        );

        return $roles;
    }
}
