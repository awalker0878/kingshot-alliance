<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Services;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\ValueObjects\EventTargetReference;

final readonly class EventParticipantAuthorization
{
    public function __construct(
        private AllianceReferenceQuery $alliances,
        private RosterEntryQuery $roster,
    ) {}

    public function eligible(Event $event, PlayerReference $player): bool
    {
        return match ($event->scopeEnum()) {
            EventScope::Player => (string) $event->player_id === $player->playerId,
            EventScope::Kingdom => (string) $event->kingdom_id === $player->kingdomId,
            EventScope::Alliance => $this->eligibleForAlliance((string) $event->alliance_id, $player),
        };
    }

    public function eligibleAgainstTarget(
        EventTargetReference $target,
        PlayerReference $player,
        bool $activeRosterPresence = false,
    ): bool {
        return match ($target->scope) {
            EventScope::Player => $target->playerId === $player->playerId,
            EventScope::Kingdom => $target->kingdomId === $player->kingdomId,
            EventScope::Alliance => $target->allianceId !== null
                && $target->kingdomId === $player->kingdomId
                && $activeRosterPresence,
        };
    }

    private function eligibleForAlliance(string $allianceId, PlayerReference $player): bool
    {
        if ($allianceId === '') {
            return false;
        }

        $alliance = $this->alliances->find($allianceId);

        return $alliance !== null
            && $alliance->active()
            && $alliance->kingdomId === $player->kingdomId
            && $this->roster->hasActiveRosterPresence($allianceId, $player->playerId);
    }
}
