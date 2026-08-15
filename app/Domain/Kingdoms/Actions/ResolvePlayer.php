<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use Illuminate\Validation\ValidationException;

final class ResolvePlayer
{
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

            $attributes = [
                'current_kingdom_id' => $alliance->kingdom_id,
                'current_name' => $name,
            ];
            if ($stableId !== null && $expected->game_player_id === null) {
                $attributes['game_player_id'] = $stableId;
            }

            $expected->forceFill($attributes)->save();

            return $expected;
        }

        if ($stableId !== null) {
            $existing = Player::query()
                ->where('game_player_id', $stableId)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Player) {
                $this->assertKingdomCanBeResolved($existing, $alliance);

                $existing->forceFill([
                    'current_kingdom_id' => $alliance->kingdom_id,
                    'current_name' => $name,
                ])->save();

                return $existing;
            }
        }

        return Player::query()->create([
            'current_kingdom_id' => $alliance->kingdom_id,
            'game_player_id' => $stableId,
            'current_name' => $name,
        ]);
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

        $hasActiveMembership = AllianceMembership::query()
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->exists();

        if ($hasActiveMembership) {
            throw ValidationException::withMessages([
                'game_player_id' => 'That Player has an active Alliance membership. End or transfer the membership before changing Kingdoms.',
            ]);
        }

        $hasIncompatibleRoster = AllianceRosterEntry::query()
            ->where('player_id', $player->id)
            ->whereIn('state', [RosterState::Active->value, RosterState::Tracked->value])
            ->whereHas('alliance', fn ($query) => $query->where('kingdom_id', '!=', $alliance->kingdom_id))
            ->exists();

        if ($hasIncompatibleRoster) {
            throw ValidationException::withMessages([
                'game_player_id' => 'That Player is active or tracked on a roster in another Kingdom. Resolve that roster before changing Kingdoms.',
            ]);
        }
    }
}
