<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Alliance\Membership\Queries\ActiveAllianceScopeQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;

final readonly class EventVisibilityResolver
{
    public function __construct(
        private AllianceOperationsAuthorization $allianceAuthorization,
        private KingdomOperationsAuthorization $kingdomAuthorization,
        private ActiveAllianceScopeQuery $allianceScope,
        private RosterEntryQuery $roster,
        private PlayerReferenceQuery $players,
    ) {}

    /** @return array{alliance:list<string>,player:list<string>,kingdom:list<string>} */
    public function targetIds(PlayerReference $actor): array
    {
        return $this->resolveTargets(
            $actor,
            OperationsPermission::EventAllianceView,
            OperationsPermission::EventPlayerManage,
            OperationsPermission::EventKingdomView,
        );
    }

    /** @return array{alliance:list<string>,player:list<string>,kingdom:list<string>} */
    public function manageableTargetIds(PlayerReference $actor): array
    {
        return $this->resolveTargets(
            $actor,
            OperationsPermission::EventAllianceManage,
            OperationsPermission::EventPlayerManage,
            OperationsPermission::EventKingdomManage,
        );
    }

    /** @return array{alliance:list<string>,player:list<string>,kingdom:list<string>} */
    private function resolveTargets(
        PlayerReference $actor,
        OperationsPermission $alliancePermission,
        OperationsPermission $playerPermission,
        OperationsPermission $kingdomPermission,
    ): array {
        $allianceIds = [];
        $managedPlayerIds = [];
        $scope = $this->allianceScope->findForPlayer($actor->playerId, $actor->kingdomId);

        if ($scope !== null) {
            if ($this->allianceAuthorization->allows($actor->playerId, $scope->allianceId, $alliancePermission)) {
                $allianceIds[] = $scope->allianceId;
            }

            if ($this->allianceAuthorization->allows($actor->playerId, $scope->allianceId, $playerPermission)) {
                $playerIds = $this->roster->activePlayerIds($scope->allianceId);
                foreach ($this->players->byIds($playerIds) as $player) {
                    if ($player->kingdomId === $actor->kingdomId) {
                        $managedPlayerIds[] = $player->playerId;
                    }
                }
            }
        }

        $kingdomIds = $this->kingdomAuthorization->allows($actor->playerId, $actor->kingdomId, $kingdomPermission)
            ? [$actor->kingdomId]
            : [];

        return [
            'alliance' => array_values(array_unique($allianceIds)),
            'player' => array_values(array_unique([$actor->playerId, ...$managedPlayerIds])),
            'kingdom' => $kingdomIds,
        ];
    }
}
