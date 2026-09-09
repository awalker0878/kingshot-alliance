<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Services;

use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferMutationContext;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class TransferWriteState
{
    public function __construct(
        private AllianceAuthorityFactsQuery $allianceAuthority,
        private KingdomReferenceQuery $kingdoms,
    ) {}

    public function lockAuthority(string $actorPlayerId, string $allianceId, ?string $completionKingdomId = null): TransferMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Transfer write state must be acquired inside a database transaction.');
        }

        $facts = $this->allianceAuthority->lockCurrent($actorPlayerId, $allianceId);
        if ($facts === null) {
            throw new AuthorizationException;
        }

        $kingdomIds = [$facts->kingdomId];
        if ($completionKingdomId !== null && $completionKingdomId !== $facts->kingdomId) {
            $kingdomIds[] = $completionKingdomId;
        }
        sort($kingdomIds, SORT_STRING);
        foreach ($kingdomIds as $kingdomId) {
            try {
                $kingdomId === $facts->kingdomId
                    ? $this->kingdoms->lockActiveShared($kingdomId)
                    : $this->kingdoms->lockCurrentShared($kingdomId);
            } catch (ModelNotFoundException) {
                if ($kingdomId === $facts->kingdomId) {
                    throw new AuthorizationException;
                }
                throw ValidationException::withMessages(['completion' => 'The transfer destination Kingdom is unavailable.']);
            }
        }

        // The current active membership is held under the exclusive Alliance
        // barrier. Identity lifecycle owners cannot move/release/reconcile this
        // actor while that membership remains active. This read does not mutate
        // Player and must not acquire an actor lock before later target scopes.
        $player = Player::query()
            ->whereKey($actorPlayerId)
            ->whereNull('canonical_player_id')
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
