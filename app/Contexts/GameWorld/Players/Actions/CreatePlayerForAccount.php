<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\ValueObjects\AuditPrincipal;
use Illuminate\Support\Facades\DB;

final readonly class CreatePlayerForAccount
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private PersistPlayerIdentity $persist,
        private ClaimPlayerAccount $claim,
    ) {}

    public function handle(int $userId, string $kingdomId, string $name, ?string $gamePlayerId): PlayerReference
    {
        return DB::transaction(function () use ($userId, $kingdomId, $name, $gamePlayerId): PlayerReference {
            $this->accounts->lockActive($userId);
            $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
            $stableId = $stableId === '' ? null : $stableId;
            $actor = AuditPrincipal::user($userId);
            $player = $this->persist->handle(
                $kingdomId,
                $name,
                $stableId,
                source: PlayerIdentitySource::Manual,
                actor: $actor,
                reason: 'Governor registered by account owner.',
                expectedExistingOwnerUserId: $userId,
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
