<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\AuditTrail\ValueObjects\AuditPrincipal;
use Illuminate\Support\Facades\DB;

final readonly class ActivatePlayer
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(int $userId, string $playerId, ?string $previousPlayerId): PlayerReference
    {
        return DB::transaction(function () use ($userId, $playerId, $previousPlayerId): PlayerReference {
            $currentPlayer = Player::query()
                ->whereKey($playerId)
                ->where('user_id', $userId)
                ->sharedLock()
                ->firstOrFail();

            $this->audit->record(
                event: 'player.context_changed',
                actor: AuditPrincipal::user($userId),
                subject: $currentPlayer,
                metadata: [
                    'previous_player_id' => $previousPlayerId,
                    'player_id' => (string) $currentPlayer->id,
                ],
            );

            return new PlayerReference(
                playerId: (string) $currentPlayer->id,
                userId: $currentPlayer->user_id === null ? null : (int) $currentPlayer->user_id,
                kingdomId: (string) $currentPlayer->current_kingdom_id,
                currentName: (string) $currentPlayer->current_name,
                gamePlayerId: $currentPlayer->game_player_id === null ? null : (string) $currentPlayer->game_player_id,
            );
        });
    }
}
