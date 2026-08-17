<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Services;

use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferMutationContext;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class TransferWriteState
{
    public function __construct(private AllianceAuthorityFactsQuery $allianceAuthority) {}

    public function lockAuthority(string $actorPlayerId, string $allianceId): TransferMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Transfer write state must be acquired inside a database transaction.');
        }

        $facts = $this->allianceAuthority->lockCurrent($actorPlayerId, $allianceId);
        if ($facts === null) {
            throw new AuthorizationException;
        }

        $player = Player::query()
            ->whereKey($actorPlayerId)
            ->lockForUpdate()
            ->firstOrFail();

        if ((string) $player->current_kingdom_id !== $facts->kingdomId) {
            throw new AuthorizationException;
        }

        return new TransferMutationContext(
            actor: new PlayerReference(
                playerId: (string) $player->id,
                userId: $player->user_id === null ? null : (int) $player->user_id,
                kingdomId: (string) $player->current_kingdom_id,
                currentName: (string) $player->current_name,
                gamePlayerId: $player->game_player_id === null ? null : (string) $player->game_player_id,
            ),
            allianceAuthority: $facts,
        );
    }
}
