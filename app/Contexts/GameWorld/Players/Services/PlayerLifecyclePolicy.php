<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Services;

use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Validation\ValidationException;

final readonly class PlayerLifecyclePolicy
{
    public function __construct(
        private PlayerMembershipQuery $memberships,
        private RosterEntryQuery $roster,
        private KingdomAuthorityFactsQuery $governance,
    ) {}

    public function assertKingdomMoveAllowed(Player $player, string $targetKingdomId): void
    {
        $currentKingdomId = (string) $player->current_kingdom_id;
        if ($currentKingdomId === $targetKingdomId) {
            return;
        }

        $playerId = (string) $player->id;
        if ($this->governance->hasActiveAssignmentsForPlayer($playerId, $currentKingdomId)) {
            throw ValidationException::withMessages(['kingdom' => 'That Player still has effective Kingdom roles in the current Kingdom. Revoke those roles before changing Kingdoms.']);
        }
        if ($this->memberships->hasAnyActiveForPlayer($playerId)) {
            throw ValidationException::withMessages(['kingdom' => 'That Player has an active Alliance membership. End or transfer the membership before changing Kingdoms.']);
        }
        if ($this->roster->hasActiveOrTrackedOutsideKingdom($playerId, $targetKingdomId)) {
            throw ValidationException::withMessages(['kingdom' => 'That Player is active or tracked on a roster in another Kingdom. Resolve that roster before changing Kingdoms.']);
        }
    }

    /** @return list<string> */
    public function releaseBlockers(Player $player): array
    {
        $playerId = (string) $player->id;
        $kingdomId = (string) $player->current_kingdom_id;
        $blockers = [];

        if ($this->governance->hasActiveAssignmentsForPlayer($playerId, $kingdomId)) {
            $blockers[] = 'Effective Kingdom roles must be revoked first.';
        }
        if ($this->memberships->hasAnyActiveForPlayer($playerId)) {
            $blockers[] = 'Active Alliance membership must be ended or transferred first.';
        }
        if ($this->roster->hasAnyActiveOrTrackedForPlayer($playerId)) {
            $blockers[] = 'Active or tracked Alliance roster presence must be resolved first.';
        }

        return $blockers;
    }

    public function assertReleaseAllowed(Player $player): void
    {
        $blockers = $this->releaseBlockers($player);
        if ($blockers !== []) {
            throw ValidationException::withMessages(['player' => implode(' ', $blockers)]);
        }
    }

    public function assertReconciliationDuplicateIsDormant(Player $duplicate): void
    {
        $blockers = $this->releaseBlockers($duplicate);
        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'duplicate_player_id' => 'The duplicate Player still has active operational dependencies. '.implode(' ', $blockers),
            ]);
        }
    }
}
