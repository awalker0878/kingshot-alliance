<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Queries;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Database\Eloquent\Collection;

final class PlayerOwnershipQuery
{
    /** @return Collection<int, Player> */
    public function allFor(User $user): Collection
    {
        return Player::query()
            ->where('user_id', $user->id)
            ->with('currentKingdom')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, Player> */
    public function upTo(User $user, int $limit): Collection
    {
        return Player::query()
            ->where('user_id', $user->id)
            ->with('currentKingdom')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    public function find(User $user, string $playerId): ?Player
    {
        return Player::query()
            ->whereKey($playerId)
            ->where('user_id', $user->id)
            ->with('currentKingdom')
            ->first();
    }

    public function countFor(User $user): int
    {
        return Player::query()
            ->where('user_id', $user->id)
            ->count();
    }

    public function owns(User $user, Player $player): bool
    {
        return $player->user_id !== null && (int) $player->user_id === (int) $user->id;
    }
}
