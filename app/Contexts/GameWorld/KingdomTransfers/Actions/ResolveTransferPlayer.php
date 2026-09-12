<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ResolveTransferPlayer
{
    public function __construct(
        private PersistPlayerIdentity $playerIdentity,
        private PlayerMembershipQuery $memberships,
        private RosterEntryQuery $roster,
        private KingdomAuthorityFactsQuery $governance,
        private KingdomReferenceQuery $kingdoms,
    ) {}

    public function handle(string $sourceKingdomId, string $name, ?string $gamePlayerId, ?string $currentPlayerId = null): PlayerReference
    {
        $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
        $stableId = $stableId === '' ? null : $stableId;

        return DB::transaction(function () use ($sourceKingdomId, $name, $stableId, $currentPlayerId): PlayerReference {
            try {
                $this->kingdoms->lockActiveShared($sourceKingdomId);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages(['source_kingdom' => 'The selected source Kingdom is archived or unavailable.']);
            }
            $current = $currentPlayerId === null ? null : Player::query()
                ->whereKey($currentPlayerId)
                ->whereNull('canonical_player_id')
                ->lockForUpdate()
                ->firstOrFail();

            $player = $current;
            if ($stableId !== null && $current instanceof Player) {
                if ($current->game_player_id !== null && $current->game_player_id !== $stableId) {
                    throw ValidationException::withMessages(['game_player_id' => 'Withdraw and recreate the participant to change the Player identity.']);
                }
                // A conflicting identity is only a rejection witness. Locking
                // it after the current Player creates opposing edit cycles.
                if (Player::query()->where('game_player_id', $stableId)->whereNull('canonical_player_id')->where('id', '<>', $current->id)->exists()) {
                    throw ValidationException::withMessages(['game_player_id' => 'That game Player ID belongs to a different Player. Withdraw and recreate the participant to change identity.']);
                }
            } elseif ($stableId !== null) {
                $player = Player::query()->where('game_player_id', $stableId)->whereNull('canonical_player_id')->lockForUpdate()->first();
            }

            if ($player instanceof Player) {
                $this->assertKingdomCanBeObserved((string) $player->id, (string) $player->current_kingdom_id, $sourceKingdomId);
            }

            return $this->playerIdentity->handle(
                $sourceKingdomId,
                trim($name),
                $stableId,
                $player instanceof Player ? (string) $player->id : null,
            );
        });
    }

    private function assertKingdomCanBeObserved(string $playerId, string $currentKingdomId, string $sourceKingdomId): void
    {
        if ($currentKingdomId === $sourceKingdomId) {
            return;
        }
        if ($this->governance->hasActiveAssignmentsForPlayer($playerId, $currentKingdomId)) {
            throw ValidationException::withMessages(['source_kingdom' => 'That Player still has effective Kingdom roles in the current Kingdom. Revoke those roles before changing the Player source Kingdom.']);
        }
        if ($this->memberships->hasAnyActiveForPlayer($playerId)) {
            throw ValidationException::withMessages(['source_kingdom' => 'That Player has an active Alliance membership. End the membership before changing the Player source Kingdom.']);
        }
        if ($this->roster->hasActiveOrTrackedOutsideKingdom($playerId, $sourceKingdomId)) {
            throw ValidationException::withMessages(['source_kingdom' => 'That Player is active or tracked on a roster in another Kingdom. Resolve that roster before changing the Player source Kingdom.']);
        }
    }
}
