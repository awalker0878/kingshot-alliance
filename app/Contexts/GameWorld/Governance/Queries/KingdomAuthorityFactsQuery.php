<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Queries;

use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAuthorityFacts;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\Access\Models\Permission;
use Illuminate\Support\Facades\DB;
use LogicException;

final class KingdomAuthorityFactsQuery
{
    public function findCurrent(string $playerId, string $kingdomId): ?KingdomAuthorityFacts
    {
        $player = Player::query()->whereKey($playerId)->first();
        if (! $player instanceof Player || (string) $player->current_kingdom_id !== $kingdomId || $player->canonical_player_id !== null) {
            return null;
        }

        if (! Kingdom::query()->whereKey($kingdomId)->where('status', KingdomStatus::Active)->exists()) {
            return null;
        }

        return $this->snapshot($playerId, $kingdomId);
    }

    public function lockCurrent(string $playerId, string $kingdomId): ?KingdomAuthorityFacts
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Kingdom authority must be locked inside an existing database transaction.');
        }

        // The owning lifecycle scope precedes actor and assignment state.
        $kingdom = Kingdom::query()->whereKey($kingdomId)->sharedLock()->first();
        if (! $kingdom instanceof Kingdom || $kingdom->status !== KingdomStatus::Active) {
            return null;
        }
        $player = Player::query()->whereKey($playerId)->lockForUpdate()->first();
        if (! $player instanceof Player || (string) $player->current_kingdom_id !== $kingdomId || $player->canonical_player_id !== null) {
            return null;
        }

        return $this->snapshot($playerId, $kingdomId);
    }

    public function hasActiveAssignmentsForPlayer(string $playerId, string $kingdomId): bool
    {
        return KingdomRoleAssignment::query()
            ->effective()
            ->where('player_id', $playerId)
            ->where('kingdom_id', $kingdomId)
            ->exists();
    }

    /**
     * Current audience candidates only. Callers must reacquire authority before writing.
     * DISTINCT precedes LIMIT so multiple effective roles cannot consume a page.
     *
     * @return list<string>
     */
    public function playerIdsWithPermissionAfter(
        string $kingdomId,
        string $permissionKey,
        ?string $afterPlayerId,
        int $limit,
    ): array {
        return array_values(KingdomRoleAssignment::query()
            ->effective()
            ->where('kingdom_id', $kingdomId)
            ->whereHas('player', static function ($query) use ($kingdomId): void {
                $query->where('current_kingdom_id', $kingdomId)
                    ->whereNotNull('user_id')->whereNull('canonical_player_id');
            })
            ->whereHas('role.permissions', static function ($query) use ($permissionKey): void {
                $query->where('permissions.key', $permissionKey);
            })
            ->when($afterPlayerId !== null, static fn ($query) => $query->where('player_id', '>', $afterPlayerId))
            ->distinct()
            ->orderBy('player_id')
            ->limit(max(1, min(1000, $limit)))
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->all());
    }

    private function snapshot(string $playerId, string $kingdomId): KingdomAuthorityFacts
    {
        // SQL subqueries reduce arbitrarily many historical assignments to the
        // deployed permission registry; no assignment or role collection is hydrated.
        $roles = KingdomRoleAssignment::query()->effective()
            ->where('kingdom_id', $kingdomId)->where('player_id', $playerId)
            ->select('kingdom_role_id');
        $permissionIds = DB::table('kingdom_role_permissions')
            ->whereIn('kingdom_role_id', $roles)->select('permission_id');
        $keys = Permission::query()->whereNotNull('owner_key')->whereIn('id', $permissionIds)
            ->orderBy('key')->limit(501)->pluck('key')->all();
        if (count($keys) > 500) {
            throw new LogicException('The Kingdom permission registry exceeds its supported authority bound.');
        }

        return new KingdomAuthorityFacts($playerId, $kingdomId, array_values(array_map(static fn ($key): string => (string) $key, $keys)));
    }
}
