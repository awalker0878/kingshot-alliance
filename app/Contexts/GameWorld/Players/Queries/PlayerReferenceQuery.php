<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Queries;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

final class PlayerReferenceQuery
{
    public function find(string $playerId): ?PlayerReference
    {
        $player = Player::query()->with('currentKingdom:id,number')->find($playerId);

        return $player instanceof Player ? $this->snapshot($player) : null;
    }

    public function require(string $playerId): PlayerReference
    {
        return $this->snapshot(Player::query()->with('currentKingdom:id,number')->findOrFail($playerId));
    }

    public function lockCurrent(string $playerId): PlayerReference
    {
        $player = Player::query()->whereKey($playerId)->lockForUpdate()->firstOrFail();
        $player->load('currentKingdom:id,number');

        return $this->snapshot($player);
    }

    /** @return list<PlayerReference> */
    public function ownedByUser(int $userId): array
    {
        return Player::query()
            ->where('user_id', $userId)
            ->with('currentKingdom:id,number')
            ->orderBy('id')
            ->get()
            ->map(fn (Player $player): PlayerReference => $this->snapshot($player))
            ->values()
            ->all();
    }


    public function findOwnedByUser(int $userId, string $playerId): ?PlayerReference
    {
        $player = Player::query()
            ->whereKey($playerId)
            ->where('user_id', $userId)
            ->with('currentKingdom:id,number')
            ->first();

        return $player instanceof Player ? $this->snapshot($player) : null;
    }

    /** @return list<PlayerReference> */
    public function ownedByUserUpTo(int $userId, int $limit): array
    {
        return Player::query()
            ->where('user_id', $userId)
            ->with('currentKingdom:id,number')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (Player $player): PlayerReference => $this->snapshot($player))
            ->values()
            ->all();
    }


    /**
     * @param list<string> $playerIds
     * @return array<string, PlayerReference>
     */
    public function byIds(array $playerIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (string $playerId): string => trim($playerId),
            $playerIds,
        ), static fn (string $playerId): bool => $playerId !== '')));

        if ($ids === []) {
            return [];
        }

        $references = [];
        foreach (Player::query()
            ->whereIn('id', $ids)
            ->with('currentKingdom:id,number')
            ->get() as $player) {
            $reference = $this->snapshot($player);
            $references[$reference->playerId] = $reference;
        }

        return $references;
    }

    /** @return list<PlayerReference> */
    public function inKingdom(string $kingdomId): array
    {
        return Player::query()
            ->where('current_kingdom_id', $kingdomId)
            ->with('currentKingdom:id,number')
            ->orderBy('current_name')
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
            kingdomNumber: $player->currentKingdom?->number === null ? null : (int) $player->currentKingdom->number,
        );
    }
}
