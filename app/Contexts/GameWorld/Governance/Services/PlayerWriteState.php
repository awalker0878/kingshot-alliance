<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use App\Contexts\GameWorld\Governance\ValueObjects\PlayerMutationContext;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Policy-free write-state acquisition for the current Player principal.
 * Authorization remains with the owning capability; this service only prevents
 * stale Player state from being used during a transaction.
 */
final class PlayerWriteState
{
    public function lockActor(Player $actor): PlayerMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Player write state must be acquired inside a database transaction.');
        }

        $currentActor = Player::query()
            ->whereKey($actor->id)
            ->lockForUpdate()
            ->firstOrFail();

        return new PlayerMutationContext($currentActor);
    }
}
