<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Services\OwnedPlayerWriteState;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\ValueObjects\AuditPrincipal;
use Illuminate\Support\Facades\DB;

final readonly class MoveOwnedPlayerToKingdom
{
    public function __construct(
        private PersistPlayerIdentity $persist,
        private OwnedPlayerWriteState $writeState,
    ) {}

    public function handle(int $userId, string $playerId, string $targetKingdomId): PlayerReference
    {
        return DB::transaction(function () use ($userId, $playerId, $targetKingdomId): PlayerReference {
            $player = $this->writeState->lock($userId, $playerId, $targetKingdomId);

            return $this->persist->handle(
                $targetKingdomId,
                (string) $player->current_name,
                $player->game_player_id === null ? null : (string) $player->game_player_id,
                $playerId,
                PlayerIdentitySource::Manual,
                actor: AuditPrincipal::user($userId),
                reason: 'Governor Kingdom changed by account owner.',
            );
        });
    }
}
