<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use App\Contexts\GameWorld\Governance\ValueObjects\KingdomMutationContext;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Policy-free transaction-time state acquisition for Kingdom writes. */
final class KingdomWriteState
{
    public function lockActiveScope(string $actorPlayerId, string $kingdomId): KingdomMutationContext
    {
        return $this->lockScope($actorPlayerId, $kingdomId, false);
    }

    public function lockExclusiveScope(string $actorPlayerId, string $kingdomId): KingdomMutationContext
    {
        return $this->lockScope($actorPlayerId, $kingdomId, true);
    }

    private function lockScope(string $actorPlayerId, string $kingdomId, bool $exclusiveKingdom): KingdomMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Kingdom write state must be acquired inside a database transaction.');
        }

        $query = Kingdom::query()->whereKey($kingdomId);
        $currentKingdom = $exclusiveKingdom
            ? $query->lockForUpdate()->firstOrFail()
            : $query->sharedLock()->firstOrFail();

        $currentActor = Player::query()
            ->whereKey($actorPlayerId)
            ->lockForUpdate()
            ->firstOrFail();

        if ((string) $currentActor->current_kingdom_id !== (string) $currentKingdom->id) {
            throw new AuthorizationException;
        }

        return new KingdomMutationContext($currentKingdom, $currentActor);
    }
}
