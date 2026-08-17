<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Support\Facades\DB;

final class ReleasePlayersFromAccount
{
    /** @param list<string> $playerIds */
    public function handle(int $userId, array $playerIds): void
    {
        if ($playerIds === []) {
            return;
        }

        DB::transaction(function () use ($userId, $playerIds): void {
            $players = Player::query()
                ->whereIn('id', $playerIds)
                ->where('user_id', $userId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($players as $player) {
                $player->forceFill(['user_id' => null])->save();
            }
        });
    }
}
