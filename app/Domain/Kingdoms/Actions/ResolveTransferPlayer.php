<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Validation\ValidationException;

final class ResolveTransferPlayer
{
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

        if (! $player instanceof Player) {
            return Player::query()->create([
                'current_kingdom_id' => $sourceKingdom->id,
                'game_player_id' => $stableId,
                'current_name' => trim($name),
            ]);
        }

        $this->assertKingdomCanBeObserved($player, $sourceKingdom);

        $attributes = [
            'current_kingdom_id' => $sourceKingdom->id,
            'current_name' => trim($name),
        ];
        if ($stableId !== null && $player->game_player_id === null) {
            $attributes['game_player_id'] = $stableId;
        }

        $player->forceFill($attributes)->save();

        return $player;
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
