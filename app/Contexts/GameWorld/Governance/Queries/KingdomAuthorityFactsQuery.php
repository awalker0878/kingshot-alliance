<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Queries;

use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAuthorityFacts;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Support\Facades\DB;
use LogicException;

final class KingdomAuthorityFactsQuery
{
    public function findCurrent(string $playerId, string $kingdomId): ?KingdomAuthorityFacts
    {
        $player = Player::query()->whereKey($playerId)->first();
        if (! $player instanceof Player || (string) $player->current_kingdom_id !== $kingdomId) {
            return null;
        }

        if (! Kingdom::query()->whereKey($kingdomId)->exists()) {
            return null;
        }

        return $this->snapshot($playerId, $kingdomId, false);
    }

    public function lockCurrent(string $playerId, string $kingdomId): ?KingdomAuthorityFacts
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Kingdom authority must be locked inside an existing database transaction.');
        }

        $player = Player::query()->whereKey($playerId)->lockForUpdate()->first();
        if (! $player instanceof Player || (string) $player->current_kingdom_id !== $kingdomId) {
            return null;
        }

        if (! Kingdom::query()->whereKey($kingdomId)->sharedLock()->exists()) {
            return null;
        }

        return $this->snapshot($playerId, $kingdomId, true);
    }

    /** @return list<string> */
    public function playerIdsWithPermission(string $kingdomId, string $permissionKey): array
    {
        return KingdomRoleAssignment::query()
            ->where('kingdom_id', $kingdomId)
            ->whereHas('role.permissions', static function ($query) use ($permissionKey): void {
                $query->where('permissions.key', $permissionKey);
            })
            ->orderBy('player_id')
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function snapshot(string $playerId, string $kingdomId, bool $lock): KingdomAuthorityFacts
    {
        $assignments = KingdomRoleAssignment::query()
            ->where('kingdom_id', $kingdomId)
            ->where('player_id', $playerId)
            ->orderBy('id');
        if ($lock) {
            $assignments->lockForUpdate();
        }
        $roleIds = $assignments->pluck('kingdom_role_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        if ($roleIds === []) {
            return new KingdomAuthorityFacts($playerId, $kingdomId, []);
        }

        $roles = KingdomRole::query()->whereIn('id', $roleIds)->with('permissions');
        if ($lock) {
            $roles->sharedLock();
        }
        $permissionKeys = $roles->get()
            ->flatMap(static fn (KingdomRole $role) => $role->permissions->pluck('key'))
            ->map(static fn ($key): string => (string) $key)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return new KingdomAuthorityFacts($playerId, $kingdomId, $permissionKeys);
    }
}
