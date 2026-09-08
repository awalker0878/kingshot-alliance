<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\ValueObjects\AuditPrincipal;

final readonly class MoveOwnedPlayerToKingdom
{
    public function __construct(private PersistPlayerIdentity $persist) {}

    public function handle(int $userId, string $playerId, string $targetKingdomId): PlayerReference
    {
        $player = Player::query()
            ->whereKey($playerId)
            ->where('user_id', $userId)
            ->whereNull('canonical_player_id')
            ->firstOrFail();

        return $this->persist->handle(
            $targetKingdomId,
            (string) $player->current_name,
            $player->game_player_id === null ? null : (string) $player->game_player_id,
            $playerId,
            PlayerIdentitySource::Manual,
            actor: AuditPrincipal::user($userId),
            reason: 'Governor Kingdom changed by account owner.',
        );
    }
}
