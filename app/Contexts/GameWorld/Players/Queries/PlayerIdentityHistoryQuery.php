<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Queries;

use App\Contexts\GameWorld\Players\Models\PlayerIdentityHistory;

final class PlayerIdentityHistoryQuery
{
    /**
     * @return list<array{
     *   id:string,userId:int|null,kingdomId:string,name:string,gamePlayerId:string|null,
     *   validFrom:string,validTo:string|null,sourceType:string,sourceReference:string|null,
     *   observedAt:string|null,confidenceBasisPoints:int|null,reason:string|null
     * }>
     */
    public function forPlayer(string $playerId, int $limit = 50): array
    {
        return array_values(PlayerIdentityHistory::query()
            ->where('player_id', $playerId)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->limit(max(1, min(200, $limit)))
            ->get()
            ->map(static fn (PlayerIdentityHistory $history): array => [
                'id' => (string) $history->id,
                'userId' => $history->user_id === null ? null : (int) $history->user_id,
                'kingdomId' => (string) $history->kingdom_id,
                'name' => (string) $history->name,
                'gamePlayerId' => $history->game_player_id === null ? null : (string) $history->game_player_id,
                'validFrom' => $history->valid_from->toIso8601String(),
                'validTo' => $history->valid_to?->toIso8601String(),
                'sourceType' => $history->source_type->value,
                'sourceReference' => $history->source_reference,
                'observedAt' => $history->observed_at?->toIso8601String(),
                'confidenceBasisPoints' => $history->confidence_basis_points,
                'reason' => $history->reason,
            ])
            ->values()
            ->all());
    }
}
