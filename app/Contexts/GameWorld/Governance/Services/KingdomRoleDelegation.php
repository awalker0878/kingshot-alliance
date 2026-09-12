<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Shared\Infrastructure\Access\Models\Permission;
use Illuminate\Validation\ValidationException;

final readonly class KingdomRoleDelegation
{
    public function __construct(private KingdomAuthorityFactsQuery $facts) {}

    /** Caller holds the exclusive Kingdom scope through its write.
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function permissionIds(string $actorPlayerId, string $kingdomId, array $keys): array
    {
        $current = $this->facts->findCurrent($actorPlayerId, $kingdomId);
        foreach ($keys as $key) {
            if ($current === null || ! $current->hasPermissionObservedAtRead($key)) {
                throw ValidationException::withMessages(['permissions' => 'You can delegate only permissions held by the current Governor.']);
            }
        }
        $ids = Permission::query()->whereIn('key', $keys)->whereNotNull('owner_key')->pluck('id')->all();
        if (count($ids) !== count($keys)) {
            throw ValidationException::withMessages(['permissions' => 'Every permission must have a recognized owning context.']);
        }

        return array_values(array_map(static fn ($id): string => (string) $id, $ids));
    }

    public function authorizeRole(string $actorPlayerId, string $kingdomId, KingdomRole $role): void
    {
        $keys = $role->permissions()->orderBy('permissions.key')->limit(51)->pluck('permissions.key')->all();
        if (count($keys) > 50) {
            throw ValidationException::withMessages(['role' => 'The role exceeds the supported permission bound.']);
        }
        $this->permissionIds($actorPlayerId, $kingdomId, array_values(array_map(static fn ($key): string => (string) $key, $keys)));
    }
}
