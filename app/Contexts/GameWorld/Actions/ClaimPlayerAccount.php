<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Actions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClaimPlayerAccount
{
    public function handle(Player $player, User $user): Player
    {
        return DB::transaction(function () use ($player, $user): Player {
            $locked = Player::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== null && (int) $locked->user_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'player' => 'This Player belongs to another account.',
                ]);
            }

            if ($locked->user_id === null) {
                $locked->forceFill(['user_id' => $user->id])->save();
            }

            return $locked->refresh();
        });
    }
}
