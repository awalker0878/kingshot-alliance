<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Authorization\ValueObjects\KingdomMutationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class KingdomMutationAuthority
{
    /**
     * Acquire current Kingdom authority for an ordinary mutation.
     *
     * The Kingdom row is shared-locked so ordinary writes can proceed concurrently,
     * while the actor Player row is the exclusive authority/state anchor. Kingdom
     * role assignment/removal must use the same Player anchor for serialization.
     */
    public function require(
        Player $actor,
        Kingdom $kingdom,
        PermissionKey $permission,
    ): KingdomMutationContext {
        return $this->acquire($actor, $kingdom, $permission, false);
    }

    /** Acquire an exclusive Kingdom parent lock for a true Kingdom-wide invariant. */
    public function requireExclusive(
        Player $actor,
        Kingdom $kingdom,
        PermissionKey $permission,
    ): KingdomMutationContext {
        return $this->acquire($actor, $kingdom, $permission, true);
    }

    private function acquire(
        Player $actor,
        Kingdom $kingdom,
        PermissionKey $permission,
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
                $query->where('permissions.key', $permission->value);
            })
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException;
        }

        return new KingdomMutationContext($currentKingdom, $currentActor);
    }
}
