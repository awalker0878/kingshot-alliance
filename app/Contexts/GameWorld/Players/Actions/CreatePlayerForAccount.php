<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\ValueObjects\AuditPrincipal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreatePlayerForAccount
{
    public function __construct(
        private PersistPlayerIdentity $persist,
        private ClaimPlayerAccount $claim,
    ) {}

    public function handle(int $userId, string $kingdomId, string $name, ?string $gamePlayerId): PlayerReference
    {
        return DB::transaction(function () use ($userId, $kingdomId, $name, $gamePlayerId): PlayerReference {
            $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
            $stableId = $stableId === '' ? null : $stableId;
            if ($stableId !== null) {
                $existing = Player::query()
                    ->where('game_player_id', $stableId)
                    ->whereNull('canonical_player_id')
                    ->lockForUpdate()
                    ->first();
                if ($existing instanceof Player && ($existing->user_id === null || (int) $existing->user_id !== $userId)) {
                    throw ValidationException::withMessages([
                        'game_player_id' => 'That game Player ID already exists. Use an evidence-backed claim or recovery workflow instead of silently taking ownership.',
                    ]);
                }
            }

            $actor = AuditPrincipal::user($userId);
            $player = $this->persist->handle(
                $kingdomId,
                $name,
                $stableId,
                source: PlayerIdentitySource::Manual,
                actor: $actor,
                reason: 'Governor registered by account owner.',
            );

            return $this->claim->handleWithProvenance(
                $player->playerId,
                $userId,
                PlayerIdentitySource::Manual,
                actor: $actor,
            );
        });
    }
}
