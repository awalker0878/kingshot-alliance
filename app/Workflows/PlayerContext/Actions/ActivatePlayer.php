<?php

declare(strict_types=1);

namespace App\Workflows\PlayerContext\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ActivatePlayer
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $user, string $playerId, ?string $previousPlayerId): Player
    {
        return DB::transaction(function () use ($user, $playerId, $previousPlayerId): Player {
            $currentUser = User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
            $currentPlayer = Player::query()
                ->whereKey($playerId)
                ->where('user_id', $currentUser->id)
                ->sharedLock()
                ->firstOrFail();

            $this->audit->record(
                'player.context_changed',
                $currentUser,
                $currentPlayer,
                null,
                [
                    'previous_player_id' => $previousPlayerId,
                    'player_id' => (string) $currentPlayer->id,
                ],
            );

            return $currentPlayer;
        });
    }
}
