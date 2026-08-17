<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;

final readonly class PlayerEventAuthorization
{
    public function __construct(
        private AllianceOperationsAuthorization $allianceAuthorization,
        private RosterEntryQuery $roster,
        private PlayerReferenceQuery $players,
    ) {}

    public function allows(string $actorPlayerId, string $targetPlayerId, OperationsPermission $permission): bool
    {
        $actor = $this->players->find($actorPlayerId);
        $target = $this->players->find($targetPlayerId);
        if (! $actor instanceof PlayerReference || ! $target instanceof PlayerReference) {
            return false;
        }

        if ($actor->playerId === $target->playerId) {
            return $this->supports($permission);
        }

        if (! $this->supports($permission) || $permission === OperationsPermission::EventPlayerCreate) {
            return false;
        }

        foreach ($this->roster->activeAllianceIdsForPlayerInKingdom($target->playerId, $target->kingdomId) as $allianceId) {
            if ($this->allianceAuthorization->allows($actor->playerId, $allianceId, OperationsPermission::EventPlayerManage)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<AllianceAuthorityFacts> $managerAllianceFacts */
    public function allowsFacts(
        string $actorPlayerId,
        string $targetPlayerId,
        array $managerAllianceFacts,
        OperationsPermission $permission,
    ): bool {
        if ($actorPlayerId === $targetPlayerId) {
            return $this->supports($permission);
        }

        if (! $this->supports($permission) || $permission === OperationsPermission::EventPlayerCreate) {
            return false;
        }

        foreach ($managerAllianceFacts as $facts) {
            if ($this->allianceAuthorization->allowsFacts($facts, OperationsPermission::EventPlayerManage)) {
                return true;
            }
        }

        return false;
    }

    private function supports(OperationsPermission $permission): bool
    {
        return in_array($permission, [
            OperationsPermission::EventPlayerView,
            OperationsPermission::EventPlayerCreate,
            OperationsPermission::EventPlayerManage,
        ], true);
    }
}
