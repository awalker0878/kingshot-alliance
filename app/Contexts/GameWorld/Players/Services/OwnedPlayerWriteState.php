<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Services;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class OwnedPlayerWriteState
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private KingdomReferenceQuery $kingdoms,
    ) {}

    public function lock(int $userId, string $playerId, ?string $targetKingdomId = null): Player
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Owned Player state must be locked inside its mutation transaction.');
        }
        $this->accounts->lockActive($userId);
        $candidate = Player::query()->whereKey($playerId)->where('user_id', $userId)->whereNull('canonical_player_id')->firstOrFail();
        try {
            $this->kingdoms->lockActiveShared($targetKingdomId ?? (string) $candidate->current_kingdom_id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['kingdom' => 'The selected Kingdom is archived or unavailable.']);
        }
        $player = Player::query()->whereKey($playerId)->where('user_id', $userId)->whereNull('canonical_player_id')->lockForUpdate()->firstOrFail();
        if ((string) $player->current_kingdom_id !== (string) $candidate->current_kingdom_id) {
            throw ValidationException::withMessages(['kingdom' => 'The Governor Kingdom changed. Reload before updating this identity.']);
        }

        return $player;
    }
}
