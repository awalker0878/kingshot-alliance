<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PersistPlayerIdentity
{
    public function __construct(
        private PlayerReferenceQuery $references,
        private PlayerMembershipQuery $memberships,
        private RosterEntryQuery $roster,
    ) {}

    public function handle(string $kingdomId, string $observedName, ?string $gamePlayerId, ?string $expectedPlayerId = null): PlayerReference
    {
        $name = trim($observedName);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Player name is required.']);
        }
        $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
        $stableId = $stableId === '' ? null : $stableId;

        $playerId = DB::transaction(function () use ($kingdomId, $name, $stableId, $expectedPlayerId): string {
            Kingdom::query()->whereKey($kingdomId)->sharedLock()->firstOrFail();

            if ($expectedPlayerId !== null) {
                $player = Player::query()->whereKey($expectedPlayerId)->lockForUpdate()->firstOrFail();
                $this->assertKingdomMoveAllowed($player, $kingdomId);
                if ($stableId !== null) {
                    $owner = Player::query()->where('game_player_id', $stableId)->lockForUpdate()->first();
                    if ($owner instanceof Player && (string) $owner->id !== (string) $player->id) {
                        throw ValidationException::withMessages(['game_player_id' => 'That game Player ID belongs to a different Player.']);
                    }
                    if ($player->game_player_id !== null && $player->game_player_id !== $stableId) {
                        throw ValidationException::withMessages(['game_player_id' => 'The selected Player has a different stable game-player identifier.']);
                    }
                }
                $attributes = ['current_kingdom_id' => $kingdomId, 'current_name' => $name];
                if ($stableId !== null && $player->game_player_id === null) {
                    $attributes['game_player_id'] = $stableId;
                }
                $player->forceFill($attributes)->save();

                return (string) $player->id;
            }

            if ($stableId !== null) {
                $player = Player::query()->where('game_player_id', $stableId)->lockForUpdate()->first();
                if ($player instanceof Player) {
                    $this->assertKingdomMoveAllowed($player, $kingdomId);
                    $player->forceFill(['current_kingdom_id' => $kingdomId, 'current_name' => $name])->save();

                    return (string) $player->id;
                }
            }

            $player = Player::query()->create(['current_kingdom_id' => $kingdomId, 'game_player_id' => $stableId, 'current_name' => $name]);

            return (string) $player->id;
        });

        return $this->references->require($playerId);
    }

    private function assertKingdomMoveAllowed(Player $player, string $targetKingdomId): void
    {
        $currentKingdomId = (string) $player->current_kingdom_id;
        if ($currentKingdomId === $targetKingdomId) {
            return;
        }
        $playerId = (string) $player->id;
        if (KingdomRoleAssignment::query()->where('player_id', $playerId)->where('kingdom_id', $currentKingdomId)->exists()) {
            throw ValidationException::withMessages(['kingdom' => 'That Player still has Kingdom roles in the current Kingdom. Remove or transfer those roles before changing Kingdoms.']);
        }
        if ($this->memberships->hasAnyActiveForPlayer($playerId)) {
            throw ValidationException::withMessages(['kingdom' => 'That Player has an active Alliance membership. End or transfer the membership before changing Kingdoms.']);
        }
        if ($this->roster->hasActiveOrTrackedOutsideKingdom($playerId, $targetKingdomId)) {
            throw ValidationException::withMessages(['kingdom' => 'That Player is active or tracked on a roster in another Kingdom. Resolve that roster before changing Kingdoms.']);
        }
    }
}
