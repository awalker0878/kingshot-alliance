<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Validation\ValidationException;

final readonly class ResolveTransferPlayer
{
    public function __construct(private PersistPlayerIdentity $playerIdentity) {}

    public function handle(
        Kingdom $sourceKingdom,
        string $name,
        ?string $gamePlayerId,
        ?string $currentPlayerId = null,
    ): Player {
        $stableId = $gamePlayerId === null ? null : trim($gamePlayerId);
        $stableId = $stableId === '' ? null : $stableId;

        $current = $currentPlayerId === null
            ? null
            : Player::query()->lockForUpdate()->findOrFail($currentPlayerId);

        $player = null;
        if ($stableId !== null) {
            $player = Player::query()
                ->where('game_player_id', $stableId)
                ->lockForUpdate()
                ->first();

            if ($current instanceof Player && $player instanceof Player && $player->id !== $current->id) {
                throw ValidationException::withMessages([
                    'game_player_id' => 'That game Player ID belongs to a different Player. Withdraw and recreate the participant to change identity.',
                ]);
            }

            if ($current instanceof Player && ! $player instanceof Player) {
                if ($current->game_player_id !== null && $current->game_player_id !== $stableId) {
                    throw ValidationException::withMessages([
                        'game_player_id' => 'Withdraw and recreate the participant to change the Player identity.',
                    ]);
                }

                $player = $current;
            }
        } elseif ($current instanceof Player) {
            $player = $current;
        }

        if ($player instanceof Player) {
            $this->assertKingdomCanBeObserved($player, $sourceKingdom);
        }

        return $this->playerIdentity->handle(
            (string) $sourceKingdom->id,
            trim($name),
            $stableId,
            $player instanceof Player ? (string) $player->id : null,
        );
    }

    private function assertKingdomCanBeObserved(Player $player, Kingdom $sourceKingdom): void
    {
        if ((string) $player->current_kingdom_id === (string) $sourceKingdom->id) {
            return;
        }

        if (KingdomRoleAssignment::query()
            ->where('player_id', $player->id)
            ->where('kingdom_id', $player->current_kingdom_id)
            ->exists()) {
            throw ValidationException::withMessages([
                'source_kingdom' => 'That Player still has Kingdom roles in the current Kingdom. Remove or transfer those roles before changing the Player source Kingdom.',
            ]);
        }

        if (AllianceMembership::query()
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'source_kingdom' => 'That Player has an active Alliance membership. End the membership before changing the Player source Kingdom.',
            ]);
        }

        if (AllianceRosterEntry::query()
            ->where('player_id', $player->id)
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])
            ->whereHas('alliance', fn ($query) => $query->where('kingdom_id', '!=', $sourceKingdom->id))
            ->exists()) {
            throw ValidationException::withMessages([
                'source_kingdom' => 'That Player is active or tracked on a roster in another Kingdom. Resolve that roster before changing the Player source Kingdom.',
            ]);
        }
    }
}
