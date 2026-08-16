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
    /**
     * Acquire authority for a GameWorld-owned Kingdom mutation.
     *
     * Downstream contexts must use acquireActiveScope() and interpret their own
     * permission vocabulary after the current Player/Kingdom scope is locked.
     */
    public function require(Player $actor, Kingdom $kingdom, Permission $permission): KingdomMutationContext
    {
        $context = $this->acquire($actor, $kingdom, false);
        $this->requirePermission($context, $permission);

        return $context;
    }

    public function requireExclusive(Player $actor, Kingdom $kingdom, Permission $permission): KingdomMutationContext
    {
        $context = $this->acquire($actor, $kingdom, true);
        $this->requirePermission($context, $permission);

        return $context;
    }

    /**
     * Lock and return the current active Player/Kingdom scope without deciding a
     * downstream context's permission semantics.
     */
    public function acquireActiveScope(Player $actor, Kingdom $kingdom): KingdomMutationContext
    {
        return $this->acquire($actor, $kingdom, false);
    }

    private function acquire(
        Player $actor,
        Kingdom $kingdom,
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

        return new KingdomMutationContext($currentKingdom, $currentActor);
    }

    private function requirePermission(KingdomMutationContext $context, Permission $permission): void
    {
        $allowed = KingdomRoleAssignment::query()
            ->where('kingdom_id', $context->kingdom->id)
            ->where('player_id', $context->actor->id)
            ->whereHas('role.permissions', static function (Builder $query) use ($permission): void {
                $query->where('permissions.key', $permission->key());
            })
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
