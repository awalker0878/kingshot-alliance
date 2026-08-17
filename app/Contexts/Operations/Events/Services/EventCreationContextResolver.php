<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\ActiveAllianceScopeQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventScope;

final readonly class EventCreationContextResolver
{
    public function __construct(
        private EventAuthorization $eventAuthorization,
        private ActiveAllianceScopeQuery $allianceScope,
        private AllianceReferenceQuery $alliances,
        private KingdomReferenceQuery $kingdoms,
    ) {}

    /**
     * @return list<array{
     *   scope: string,
     *   targetId: string,
     *   label: string,
     *   allianceId?: string,
     *   kingdomId?: string,
     *   kingdomNumber?: int
     * }>
     */
    public function forPlayer(PlayerReference $actor): array
    {
        $contexts = [];

        if ($this->eventAuthorization->allows(
            $actor->playerId,
            EventScope::Player,
            $actor->playerId,
            OperationsPermission::EventPlayerCreate,
        )) {
            $contexts[] = [
                'scope' => EventScope::Player->value,
                'targetId' => $actor->playerId,
                'label' => $actor->currentName,
                'kingdomId' => $actor->kingdomId,
                'kingdomNumber' => $actor->kingdomNumber,
            ];
        }

        $activeAlliance = $this->allianceScope->findForPlayer($actor->playerId, $actor->kingdomId);
        if ($activeAlliance !== null
            && $this->eventAuthorization->allows(
                $actor->playerId,
                EventScope::Alliance,
                $activeAlliance->allianceId,
                OperationsPermission::EventAllianceCreate,
            )) {
            $alliance = $this->alliances->require($activeAlliance->allianceId);
            $contexts[] = [
                'scope' => EventScope::Alliance->value,
                'targetId' => $alliance->allianceId,
                'label' => $alliance->name,
                'allianceId' => $alliance->allianceId,
                'kingdomId' => $alliance->kingdomId,
            ];
        }

        if ($this->eventAuthorization->allows(
            $actor->playerId,
            EventScope::Kingdom,
            $actor->kingdomId,
            OperationsPermission::EventKingdomCreate,
        )) {
            $kingdom = $this->kingdoms->require($actor->kingdomId);
            $contexts[] = [
                'scope' => EventScope::Kingdom->value,
                'targetId' => $kingdom->kingdomId,
                'label' => '#'.$kingdom->number,
                'kingdomId' => $kingdom->kingdomId,
                'kingdomNumber' => $kingdom->number,
            ];
        }

        return $contexts;
    }
}
