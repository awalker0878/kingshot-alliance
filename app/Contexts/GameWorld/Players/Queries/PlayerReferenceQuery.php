<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Queries;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

final class PlayerReferenceQuery
{
    public function find(string $playerId): ?PlayerReference
    {
        $player = Player::query()->find($playerId);

        return $player instanceof Player ? $this->snapshot($player) : null;
    }

    public function require(string $playerId): PlayerReference
    {
        return $this->snapshot(Player::query()->findOrFail($playerId));
    }

    /** @return list<PlayerReference> */
    public function ownedByUser(int $userId): array
    {
        return Player::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(fn (Player $player): PlayerReference => $this->snapshot($player))
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function ownedIds(int $userId): array
    {
        return Player::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    private function snapshot(Player $player): PlayerReference
    {
        return new PlayerReference(
            playerId: (string) $player->id,
            userId: $player->user_id === null ? null : (int) $player->user_id,
            kingdomId: (string) $player->current_kingdom_id,
            currentName: (string) $player->current_name,
            gamePlayerId: $player->game_player_id === null ? null : (string) $player->game_player_id,
        );
    }
}
