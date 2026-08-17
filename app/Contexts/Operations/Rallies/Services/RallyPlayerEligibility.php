<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Services;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Participation\Services\EventParticipantAuthorization;

final readonly class RallyPlayerEligibility
{
    public function __construct(
        private EventParticipantAuthorization $events,
        private RosterEntryQuery $roster,
    ) {}

    public function eligible(Event $event, AllianceReference $alliance, PlayerReference $player): bool
    {
        return $alliance->active()
            && $alliance->kingdomId === $player->kingdomId
            && $this->events->eligible($event, $player)
            && $this->roster->hasActiveRosterPresence($alliance->allianceId, $player->playerId);
    }
}
