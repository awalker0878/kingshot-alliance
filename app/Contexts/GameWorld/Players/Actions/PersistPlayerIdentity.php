<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PersistPlayerIdentity
{
    public function handle(string $kingdomId, string $observedName, ?string $gamePlayerId, ?string $expectedPlayerId = null): Player
    {
        $name = trim($observedName);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Player name is required.']);
        }
        $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
        $stableId = $stableId === '' ? null : $stableId;

        return DB::transaction(function () use ($kingdomId, $name, $stableId, $expectedPlayerId): Player {
            Kingdom::query()->whereKey($kingdomId)->sharedLock()->firstOrFail();
            if ($expectedPlayerId !== null) {
                $player = Player::query()->whereKey($expectedPlayerId)->lockForUpdate()->firstOrFail();
                if ($stableId !== null) {
                    $owner = Player::query()->where('game_player_id', $stableId)->lockForUpdate()->first();
                    if ($owner instanceof Player && (string) $owner->id !== (string) $player->id) {
                        throw ValidationException::withMessages(['game_player_id' => 'That game Player ID belongs to a different Player.']);
                    }
                    if ($player->game_player_id !== null && $player->game_player_id !== $stableId) {
                        throw ValidationException::withMessages(['game_player_id' => 'The selected Player has a different stable game-player identifier.']);
                    }
                }
                $attributes = ['current_kingdom_id' => $kingdomId, 'current_name' => $name];
                if ($stableId !== null && $player->game_player_id === null) {
                    $attributes['game_player_id'] = $stableId;
                }
                $player->forceFill($attributes)->save();

                return $player->refresh();
            }
            if ($stableId !== null) {
                $player = Player::query()->where('game_player_id', $stableId)->lockForUpdate()->first();
                if ($player instanceof Player) {
                    $player->forceFill(['current_kingdom_id' => $kingdomId, 'current_name' => $name])->save();

                    return $player->refresh();
                }
            }

            return Player::query()->create(['current_kingdom_id' => $kingdomId, 'game_player_id' => $stableId, 'current_name' => $name]);
        });
    }
}
