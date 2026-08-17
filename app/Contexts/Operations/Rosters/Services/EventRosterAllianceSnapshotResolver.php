<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Services;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\ValueObjects\EventTargetReference;

final readonly class EventRosterAllianceSnapshotResolver
{
    public function __construct(
        private PlayerMembershipQuery $memberships,
        private AllianceReferenceQuery $alliances,
    ) {}

    public function resolve(EventTargetReference $target, PlayerReference $player): ?string
    {
        if ($target->scope === EventScope::Alliance) {
            return $target->allianceId;
        }

        $kingdomId = $target->scope === EventScope::Kingdom
            ? $target->kingdomId
            : $player->kingdomId;
        if ($kingdomId === null || $player->kingdomId !== $kingdomId) {
            return null;
        }

        $allianceIds = $this->memberships->activeAllianceIdsForPlayerInKingdom($player->playerId, $kingdomId);
        $activeAllianceIds = array_values(array_filter(
            $allianceIds,
            fn (string $allianceId): bool => $this->alliances->find($allianceId)?->active() === true,
        ));

        return count($activeAllianceIds) === 1 ? $activeAllianceIds[0] : null;
    }
}
