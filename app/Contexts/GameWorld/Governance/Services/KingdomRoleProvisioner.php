<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Shared\Access\Models\Permission;
use Illuminate\Support\Str;
use RuntimeException;

final class KingdomRoleProvisioner
{
    /** @return array<string, KingdomRole> */
    public function provision(Kingdom $kingdom): array
    {
        $permission = Permission::query()->updateOrCreate(
            ['key' => KingdomPermission::RoleManage->key()],
            [
                'id' => (string) Str::ulid(),
                'description' => KingdomPermission::RoleManage->description(),
            ],
        );

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

            if ($roleTemplate === DefaultKingdomRole::Administrator) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }

            $roles[$roleTemplate->value] = $role;
        }

        if (! isset($roles[DefaultKingdomRole::Administrator->value])) {
            throw new RuntimeException('The Kingdom Administrator role was not provisioned.');
        }

        return $roles;
    }
}
