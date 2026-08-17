<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClaimPlayerAccount
{
    public function handle(string $playerId, int $userId): PlayerReference
    {
        return DB::transaction(function () use ($playerId, $userId): PlayerReference {
            $locked = Player::query()->whereKey($playerId)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== null && (int) $locked->user_id !== $userId) {
                throw ValidationException::withMessages(['player' => 'This Player belongs to another account.']);
            }

            if ($locked->user_id === null) {
                $locked->forceFill(['user_id' => $userId])->save();
            }

            $locked->refresh();

            return new PlayerReference(
                playerId: (string) $locked->id,
                userId: $locked->user_id === null ? null : (int) $locked->user_id,
                kingdomId: (string) $locked->current_kingdom_id,
                currentName: (string) $locked->current_name,
                gamePlayerId: $locked->game_player_id === null ? null : (string) $locked->game_player_id,
            );
        });
    }
}
