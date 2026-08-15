<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomMutationContext;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Access\Contracts\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class KingdomMutationAuthority
{
    public function require(Player $actor, Kingdom $kingdom, Permission $permission): KingdomMutationContext
    {
        return $this->acquire($actor, $kingdom, $permission, false);
    }

    public function requireExclusive(Player $actor, Kingdom $kingdom, Permission $permission): KingdomMutationContext
    {
        return $this->acquire($actor, $kingdom, $permission, true);
    }

    private function acquire(
        Player $actor,
        Kingdom $kingdom,
        Permission $permission,
        bool $exclusiveKingdom,
    ): KingdomMutationContext {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Kingdom mutation authority must be acquired inside a database transaction.');
        }

        $kingdomQuery = Kingdom::query()->whereKey($kingdom->id);
        $currentKingdom = $exclusiveKingdom
            ? $kingdomQuery->lockForUpdate()->firstOrFail()
            : $kingdomQuery->sharedLock()->firstOrFail();

        $currentActor = Player::query()
            ->whereKey($actor->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ((string) $currentActor->current_kingdom_id !== (string) $currentKingdom->id) {
            throw new AuthorizationException;
        }

        $allowed = KingdomRoleAssignment::query()
            ->where('kingdom_id', $currentKingdom->id)
            ->where('player_id', $currentActor->id)
            ->whereHas('role.permissions', static function (Builder $query) use ($permission): void {
                $query->where('permissions.key', $permission->key());
            })
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException;
        }

        return new KingdomMutationContext($currentKingdom, $currentActor);
    }
}
