<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\KingdomPlayer;
use Illuminate\Validation\ValidationException;

final class ResolveKingdomPlayer
{
    public function handle(Alliance $alliance, string $observedName, ?string $gamePlayerId): KingdomPlayer
    {
        if ($alliance->kingdom_id === null) {
            throw ValidationException::withMessages([
                'kingdom' => 'The alliance must have a Kingdom before roster players can be added.',
            ]);
        }

        $name = trim($observedName);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Player name is required.']);
        }

        $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
        $stableId = $stableId === '' ? null : $stableId;

        if ($stableId !== null) {
            $existing = KingdomPlayer::query()
                ->where('kingdom_id', $alliance->kingdom_id)
                ->where('game_player_id', $stableId)
                ->first();

            if ($existing instanceof KingdomPlayer) {
                return $existing;
            }
        }

        return KingdomPlayer::query()->create([
            'kingdom_id' => $alliance->kingdom_id,
            'game_player_id' => $stableId,
            'current_name' => $name,
        ]);
    }
}
