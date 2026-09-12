<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Queries;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use LogicException;

final class PlayerReferenceQuery
{
    private const MAX_CANONICAL_DEPTH = 32;

    public function find(string $playerId): ?PlayerReference
    {
        $player = Player::query()->with('currentKingdom:id,number')->find($playerId);

        return $player instanceof Player ? $this->snapshot($player) : null;
    }

    public function require(string $playerId): PlayerReference
    {
        return $this->snapshot(Player::query()->with('currentKingdom:id,number')->findOrFail($playerId));
    }

    public function findCanonical(string $playerId): ?PlayerReference
    {
        $player = Player::query()->with('currentKingdom:id,number')->find($playerId);
        if (! $player instanceof Player) {
            return null;
        }

        return $this->snapshot($this->canonicalModel($player));
    }

    public function requireCanonical(string $playerId): PlayerReference
    {
        return $this->snapshot($this->canonicalModel(
            Player::query()->with('currentKingdom:id,number')->findOrFail($playerId),
        ));
    }

    public function lockCurrent(string $playerId): PlayerReference
    {
        $player = Player::query()
            ->whereKey($playerId)
            ->whereNull('canonical_player_id')
            ->lockForUpdate()
            ->firstOrFail();
        $player->load('currentKingdom:id,number');

        return $this->snapshot($player);
    }

    /** Revalidate a linked identity without waiting behind an inverse owner scope. */
    public function lockCurrentNowait(string $playerId): PlayerReference
    {
        $player = Player::query()->whereKey($playerId)->whereNull('canonical_player_id')
            ->lock('for update nowait')->firstOrFail();
        $player->load('currentKingdom:id,number');

        return $this->snapshot($player);
    }

    /** Stabilize identity for admission without locking another Alliance's rows. */
    public function lockCurrentShared(string $playerId): PlayerReference
    {
        $player = Player::query()
            ->whereKey($playerId)
            ->whereNull('canonical_player_id')
            ->sharedLock()
            ->firstOrFail();
        $player->load('currentKingdom:id,number');

        return $this->snapshot($player);
    }

    /** @return list<PlayerReference> */
    public function ownedByUser(int $userId): array
    {
        return array_values(Player::query()
            ->where('user_id', $userId)
            ->whereNull('canonical_player_id')
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
            ->whereNull('canonical_player_id')
            ->with('currentKingdom:id,number')
            ->first();

        return $player instanceof Player ? $this->snapshot($player) : null;
    }

    /** @return list<PlayerReference> */
    public function ownedByUserUpTo(int $userId, int $limit): array
    {
        return array_values(Player::query()
            ->where('user_id', $userId)
            ->whereNull('canonical_player_id')
            ->with('currentKingdom:id,number')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (Player $player): PlayerReference => $this->snapshot($player))
            ->values()
            ->all());
    }

    /** @return list<int> */
    public function ownerUserIdsAfter(?int $afterUserId, int $limit): array
    {
        return array_values(Player::query()
            ->select('user_id')
            ->whereNotNull('user_id')
            ->whereNull('canonical_player_id')
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
    public function ownerUserIdsWhoStartedGiftCodeAfter(string $giftCodeId, ?int $afterUserId, int $limit): array
    {
        return array_values(Player::query()
            ->select('players.user_id')
            ->join('gift_code_redemptions', 'gift_code_redemptions.player_id', '=', 'players.id')
            ->where('gift_code_redemptions.gift_code_id', $giftCodeId)
            ->whereNotNull('players.user_id')
            ->whereNull('players.canonical_player_id')
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
     * @param  array<int|string, string>  $playerIds
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

    public function findByGamePlayerId(string $gamePlayerId): ?PlayerReference
    {
        $stableId = trim($gamePlayerId);
        if ($stableId === '') {
            return null;
        }

        $player = Player::query()
            ->where('game_player_id', $stableId)
            ->whereNull('canonical_player_id')
            ->with('currentKingdom:id,number')
            ->first();

        return $player instanceof Player ? $this->snapshot($player) : null;
    }

    /** @return list<PlayerReference> */
    public function matchingGamePlayerIdInKingdom(string $kingdomId, string $gamePlayerId, int $limit = 2): array
    {
        return array_values(Player::query()
            ->where('current_kingdom_id', $kingdomId)
            ->where('game_player_id', $gamePlayerId)
            ->whereNull('canonical_player_id')
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
            ->whereNull('canonical_player_id')
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
            ->whereNull('canonical_player_id')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all());
    }

    private function canonicalModel(Player $player): Player
    {
        $visited = [];
        for ($depth = 0; $depth < self::MAX_CANONICAL_DEPTH; $depth++) {
            $id = (string) $player->id;
            if (isset($visited[$id])) {
                throw new LogicException('Circular Player canonical identity link detected.');
            }
            $visited[$id] = true;

            $canonicalId = $player->canonical_player_id === null ? null : (string) $player->canonical_player_id;
            if ($canonicalId === null) {
                return $player;
            }

            $player = Player::query()->with('currentKingdom:id,number')->findOrFail($canonicalId);
        }

        throw new LogicException('Player canonical identity chain exceeds the supported depth.');
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
            canonicalPlayerId: $player->canonical_player_id === null ? null : (string) $player->canonical_player_id,
        );
    }
}
