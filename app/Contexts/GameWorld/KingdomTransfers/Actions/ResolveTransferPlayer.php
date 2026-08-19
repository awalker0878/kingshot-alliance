<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Validation\ValidationException;

final readonly class ResolveTransferPlayer
{
    public function __construct(
        private PersistPlayerIdentity $playerIdentity,
        private PlayerMembershipQuery $memberships,
        private RosterEntryQuery $roster,
    ) {}

    public function handle(string $sourceKingdomId, string $name, ?string $gamePlayerId, ?string $currentPlayerId = null): PlayerReference
    {
        $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
        $stableId = $stableId === '' ? null : $stableId;
        $current = $currentPlayerId === null ? null : Player::query()->lockForUpdate()->findOrFail($currentPlayerId);

        $player = null;
        if ($stableId !== null) {
            $player = Player::query()->where('game_player_id', $stableId)->lockForUpdate()->first();
            if ($current instanceof Player && $player instanceof Player && $player->id !== $current->id) {
                throw ValidationException::withMessages(['game_player_id' => 'That game Player ID belongs to a different Player. Withdraw and recreate the participant to change identity.']);
            }
            if ($current instanceof Player && ! $player instanceof Player) {
                if ($current->game_player_id !== null && $current->game_player_id !== $stableId) {
                    throw ValidationException::withMessages(['game_player_id' => 'Withdraw and recreate the participant to change the Player identity.']);
                }
                $player = $current;
            }
        } elseif ($current instanceof Player) {
            $player = $current;
        }

        if ($player instanceof Player) {
            $this->assertKingdomCanBeObserved((string) $player->id, (string) $player->current_kingdom_id, $sourceKingdomId);
        }

        $persisted = $this->playerIdentity->handle(
            $sourceKingdomId,
            trim($name),
            $stableId,
            $player instanceof Player ? (string) $player->id : null,
        );

        return $persisted;
    }

    private function assertKingdomCanBeObserved(string $playerId, string $currentKingdomId, string $sourceKingdomId): void
    {
        if ($currentKingdomId === $sourceKingdomId) {
            return;
        }
        if (KingdomRoleAssignment::query()->where('player_id', $playerId)->where('kingdom_id', $currentKingdomId)->exists()) {
            throw ValidationException::withMessages(['source_kingdom' => 'That Player still has Kingdom roles in the current Kingdom. Remove or transfer those roles before changing the Player source Kingdom.']);
        }
        if ($this->memberships->hasAnyActiveForPlayer($playerId)) {
            throw ValidationException::withMessages(['source_kingdom' => 'That Player has an active Alliance membership. End the membership before changing the Player source Kingdom.']);
        }
        if ($this->roster->hasActiveOrTrackedOutsideKingdom($playerId, $sourceKingdomId)) {
            throw ValidationException::withMessages(['source_kingdom' => 'That Player is active or tracked on a roster in another Kingdom. Resolve that roster before changing the Player source Kingdom.']);
        }
    }
}
