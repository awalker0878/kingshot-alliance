<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use App\Contexts\GameWorld\Governance\ValueObjects\PlayerMutationContext;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PlayerMutationAuthority
{
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
