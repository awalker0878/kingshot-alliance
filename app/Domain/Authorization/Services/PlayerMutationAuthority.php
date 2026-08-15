<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\ValueObjects\PlayerMutationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PlayerMutationAuthority
{
    /** Acquire the exact Player as the mutation principal and state anchor. */
    public function require(Player $actor): PlayerMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Player mutation authority must be acquired inside a database transaction.');
        }

        $currentActor = Player::query()
            ->whereKey($actor->id)
            ->lockForUpdate()
            ->firstOrFail();

        return new PlayerMutationContext($currentActor);
    }
}
