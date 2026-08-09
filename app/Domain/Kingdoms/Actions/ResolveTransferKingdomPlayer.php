<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\KingdomPlayer;

final class ResolveTransferKingdomPlayer
{
    public function handle(Kingdom $sourceKingdom, string $name, ?string $gamePlayerId): ?KingdomPlayer
    {
        $gamePlayerId = $gamePlayerId === null ? null : trim($gamePlayerId);

        if ($gamePlayerId === null || $gamePlayerId === '') {
            return null;
        }

        $name = trim($name);
        $player = KingdomPlayer::query()->firstOrCreate(
            [
                'kingdom_id' => $sourceKingdom->id,
                'game_player_id' => $gamePlayerId,
            ],
            ['current_name' => $name],
        );

        if ($player->current_name !== $name) {
            $player->forceFill(['current_name' => $name])->save();
        }

        return $player;
    }
}
