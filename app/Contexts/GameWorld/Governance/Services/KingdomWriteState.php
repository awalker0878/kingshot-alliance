<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use App\Contexts\GameWorld\Governance\ValueObjects\KingdomMutationContext;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Policy-free transaction-time state acquisition for Kingdom writes. */
final class KingdomWriteState
{
    public function lockActiveScope(Player $actor, Kingdom $kingdom): KingdomMutationContext
    {
        return $this->lockScope($actor, $kingdom, false);
    }

    public function lockExclusiveScope(Player $actor, Kingdom $kingdom): KingdomMutationContext
    {
        return $this->lockScope($actor, $kingdom, true);
    }

    private function lockScope(Player $actor, Kingdom $kingdom, bool $exclusiveKingdom): KingdomMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Kingdom write state must be acquired inside a database transaction.');
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
}
