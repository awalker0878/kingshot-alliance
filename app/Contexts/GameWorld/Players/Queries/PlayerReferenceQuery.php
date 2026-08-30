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
        return array_values(Player::query()
            ->where('user_id', $userId)
            ->with('currentKingdom:id,number')
            ->orderBy('id')
            ->get()
            ->map(fn (Player $player): PlayerReference => $this->snapshot($player))
            ->values()
            ->all());
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
        return array_values(Player::query()
            ->where('user_id', $userId)
            ->with('currentKingdom:id,number')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (Player $player): PlayerReference => $this->snapshot($player))
            ->values()
            ->all());
    }

    /**
     * Return account IDs that currently own at least one Governor. The caller
     * owns the cursor; this query intentionally exposes only scalar account IDs.
     *
     * @return list<int>
     */
    public function ownerUserIdsAfter(?int $afterUserId, int $limit): array
    {
        return array_values(Player::query()
            ->select('user_id')
            ->whereNotNull('user_id')
            ->when($afterUserId !== null, static fn ($query) => $query->where('user_id', '>', $afterUserId))
            ->distinct()
            ->orderBy('user_id')
            ->limit(max(1, min(1000, $limit)))
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all());
    }

    /** @return list<int> */
    public function ownerUserIdsWhoStartedGiftCodeAfter(
        string $giftCodeId,
        ?int $afterUserId,
        int $limit,
    ): array {
        return array_values(Player::query()
            ->select('players.user_id')
            ->join('gift_code_redemptions', 'gift_code_redemptions.player_id', '=', 'players.id')
            ->where('gift_code_redemptions.gift_code_id', $giftCodeId)
            ->whereNotNull('players.user_id')
            ->when($afterUserId !== null, static fn ($query) => $query->where('players.user_id', '>', $afterUserId))
            ->distinct()
            ->orderBy('players.user_id')
            ->limit(max(1, min(1000, $limit)))
            ->pluck('players.user_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all());
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
        foreach (Player::query()->whereIn('id', $ids)->with('currentKingdom:id,number')->get() as $player) {
            $reference = $this->snapshot($player);
            $references[$reference->playerId] = $reference;
        }

        return $references;
    }

    /** @return list<PlayerReference> */
    public function matchingGamePlayerIdInKingdom(string $kingdomId, string $gamePlayerId, int $limit = 2): array
    {
        return array_values(Player::query()
            ->where('current_kingdom_id', $kingdomId)
            ->where('game_player_id', $gamePlayerId)
            ->with('currentKingdom:id,number')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (Player $player): PlayerReference => $this->snapshot($player))
            ->values()
            ->all());
    }

    /** @return list<PlayerReference> */
    public function inKingdom(string $kingdomId): array
    {
        return array_values(Player::query()
            ->where('current_kingdom_id', $kingdomId)
            ->with('currentKingdom:id,number')
            ->orderBy('current_name')
            ->get()
            ->map(fn (Player $player): PlayerReference => $this->snapshot($player))
            ->values()
            ->all());
    }

    /** @return list<string> */
    public function ownedIds(int $userId): array
    {
        return array_values(Player::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all());
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
