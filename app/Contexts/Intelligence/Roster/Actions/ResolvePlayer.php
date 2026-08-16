<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Validation\ValidationException;

final readonly class ResolvePlayer
{
    public function __construct(private PersistPlayerIdentity $playerIdentity) {}

    public function handle(
        Alliance $alliance,
        string $observedName,
        ?string $gamePlayerId,
        ?string $expectedPlayerId = null,
    ): Player {
        if ($alliance->kingdom_id === null) {
            throw ValidationException::withMessages([
                'kingdom' => 'The alliance must have a Kingdom before roster players can be added.',
            ]);
        }

        $name = trim($observedName);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Player name is required.']);
        }

        $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
        $stableId = $stableId === '' ? null : $stableId;

        if ($expectedPlayerId !== null) {
            $expected = Player::query()->lockForUpdate()->findOrFail($expectedPlayerId);

            if ($stableId !== null) {
                $stableOwner = Player::query()
                    ->where('game_player_id', $stableId)
                    ->lockForUpdate()
                    ->first();

                if ($stableOwner instanceof Player && $stableOwner->id !== $expected->id) {
                    throw ValidationException::withMessages([
                        'game_player_id' => 'That game Player ID belongs to a different Player.',
                    ]);
                }

                if ($expected->game_player_id !== null && $expected->game_player_id !== $stableId) {
                    throw ValidationException::withMessages([
                        'game_player_id' => 'The expected Player has a different stable game-player identifier.',
                    ]);
                }
            }

            $this->assertKingdomCanBeResolved($expected, $alliance);

            return $this->playerIdentity->handle(
                (string) $alliance->kingdom_id,
                $name,
                $stableId,
                (string) $expected->id,
            );
        }

        if ($stableId !== null) {
            $existing = Player::query()
                ->where('game_player_id', $stableId)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Player) {
                $this->assertKingdomCanBeResolved($existing, $alliance);

                return $this->playerIdentity->handle(
                    (string) $alliance->kingdom_id,
                    $name,
                    $stableId,
                    (string) $existing->id,
                );
            }
        }

        return $this->playerIdentity->handle((string) $alliance->kingdom_id, $name, $stableId);
    }

    private function assertKingdomCanBeResolved(Player $player, Alliance $alliance): void
    {
        if ((string) $player->current_kingdom_id === (string) $alliance->kingdom_id) {
            return;
        }

        if (KingdomRoleAssignment::query()
            ->where('player_id', $player->id)
            ->where('kingdom_id', $player->current_kingdom_id)
            ->exists()) {
            throw ValidationException::withMessages([
                'game_player_id' => 'That Player still has Kingdom roles in the current Kingdom. Remove or transfer those roles before changing Kingdoms.',
            ]);
        }

        if (AllianceMembership::query()
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'game_player_id' => 'That Player has an active Alliance membership. End or transfer the membership before changing Kingdoms.',
            ]);
        }

        if (AllianceRosterEntry::query()
            ->where('player_id', $player->id)
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])
            ->whereHas('alliance', fn ($query) => $query->where('kingdom_id', '!=', $alliance->kingdom_id))
            ->exists()) {
            throw ValidationException::withMessages([
                'game_player_id' => 'That Player is active or tracked on a roster in another Kingdom. Resolve that roster before changing Kingdoms.',
            ]);
        }
    }
}
