<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Queries;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

final class GiftCodeCatalogQuery
{
    /** @return Collection<int, GiftCode> */
    public function forPlayer(string $playerId, int $limit = 100): Collection
    {
        return $this->forPlayers([$playerId], $limit);
    }

    /**
     * @param non-empty-list<string> $playerIds
     * @return Collection<int, GiftCode>
     */
    public function forPlayers(array $playerIds, int $limit = 100): Collection
    {
        return GiftCode::query()
            ->with(['redemptions' => static function (Relation $relation) use ($playerIds): void {
                $relation->getQuery()->whereIn('player_id', $playerIds);
            }, 'provenances'])
            ->orderByDesc('discovered_at')
            ->limit(max(1, min($limit, 250)))
            ->get();
    }

    public function redemptionFor(GiftCode $giftCode, string $playerId): ?GiftCodeRedemption
    {
        $redemption = $giftCode->redemptions->first(
            static fn (GiftCodeRedemption $candidate): bool => $candidate->player_id === $playerId,
        );

        return $redemption instanceof GiftCodeRedemption ? $redemption : null;
    }
}
