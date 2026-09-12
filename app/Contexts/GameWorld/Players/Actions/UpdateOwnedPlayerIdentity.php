<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Services\OwnedPlayerWriteState;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\ValueObjects\AuditPrincipal;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOwnedPlayerIdentity
{
    public function __construct(
        private PersistPlayerIdentity $persist,
        private OwnedPlayerWriteState $writeState,
    ) {}

    public function handle(int $userId, string $playerId, string $name, ?string $gamePlayerId): PlayerReference
    {
        return DB::transaction(function () use ($userId, $playerId, $name, $gamePlayerId): PlayerReference {
            $player = $this->writeState->lock($userId, $playerId);

            return $this->persist->handle(
                (string) $player->current_kingdom_id,
                $name,
                $gamePlayerId,
                $playerId,
                PlayerIdentitySource::Manual,
                actor: AuditPrincipal::user($userId),
                reason: 'Governor identity updated by account owner.',
            );
        });
    }
}
