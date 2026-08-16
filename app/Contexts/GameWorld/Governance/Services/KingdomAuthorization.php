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

final class KingdomAuthorization
{
    public function allows(Player $player, Kingdom $kingdom, Permission $permission): bool
    {
        if ((string) $player->current_kingdom_id !== (string) $kingdom->id) {
            return false;
        }

        return $this->hasPermission($player, $kingdom, $permission);
    }

    public function require(Player $actor, Kingdom $kingdom, Permission $permission): KingdomMutationContext
    {
        $context = $this->acquire($actor, $kingdom, false);
        $this->assertAllowed($context, $permission);

        return $context;
    }

    public function requireExclusive(Player $actor, Kingdom $kingdom, Permission $permission): KingdomMutationContext
    {
        $context = $this->acquire($actor, $kingdom, true);
        $this->assertAllowed($context, $permission);

        return $context;
    }

    public function acquireActiveScope(Player $actor, Kingdom $kingdom): KingdomMutationContext
    {
        return $this->acquire($actor, $kingdom, false);
    }

    private function acquire(Player $actor, Kingdom $kingdom, bool $exclusiveKingdom): KingdomMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Kingdom transactional authorization must run inside a database transaction.');
        }

        $query = Kingdom::query()->whereKey($kingdom->id);
        $currentKingdom = $exclusiveKingdom
            ? $query->lockForUpdate()->firstOrFail()
            : $query->sharedLock()->firstOrFail();

        $currentActor = Player::query()
            ->whereKey($actor->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ((string) $currentActor->current_kingdom_id !== (string) $currentKingdom->id) {
            throw new AuthorizationException;
        }

        return new KingdomMutationContext($currentKingdom, $currentActor);
    }

    private function assertAllowed(KingdomMutationContext $context, Permission $permission): void
    {
        if (! $this->hasPermission($context->actor, $context->kingdom, $permission)) {
            throw new AuthorizationException;
        }
    }

    private function hasPermission(Player $player, Kingdom $kingdom, Permission $permission): bool
    {
        return KingdomRoleAssignment::query()
            ->where('kingdom_id', $kingdom->id)
            ->where('player_id', $player->id)
            ->whereHas('role.permissions', static function (Builder $query) use ($permission): void {
                $query->where('permissions.key', $permission->key());
            })
            ->exists();
    }
}
