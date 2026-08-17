<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Services;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\ValueObjects\EventTargetReference;
use App\Contexts\Operations\Participation\Services\EventParticipantAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class RallyWriteState
{
    public function __construct(
        private AllianceReferenceQuery $alliances,
        private RosterEntryQuery $roster,
        private PlayerReferenceQuery $players,
        private EventParticipantAuthorization $participants,
    ) {}

    public function lockAllianceForTarget(EventTargetReference $target, string $allianceId): AllianceReference
    {
        $this->assertTransaction();

        $alliance = $this->alliances->lockCurrent($allianceId);
        $valid = $alliance->active() && match ($target->scope) {
            EventScope::Alliance => $target->allianceId === $alliance->allianceId,
            EventScope::Kingdom => $target->kingdomId === $alliance->kingdomId,
            EventScope::Player => $target->playerId !== null
                && $target->kingdomId === $alliance->kingdomId
                && $this->roster->lockActiveRosterPresence($alliance->allianceId, $target->playerId),
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                'alliance_id' => 'This Alliance is not a current Rally context for the Event.',
            ]);
        }

        return $alliance;
    }

    public function lockEligiblePlayer(
        EventTargetReference $target,
        AllianceReference $alliance,
        string $playerId,
    ): PlayerReference {
        $this->assertTransaction();

        $player = $this->players->lockCurrent($playerId);
        $rallyRosterPresence = $this->roster->lockActiveRosterPresence($alliance->allianceId, $player->playerId);
        $eventRosterPresence = $target->scope === EventScope::Alliance && $target->allianceId !== null
            ? $this->roster->lockActiveRosterPresence($target->allianceId, $player->playerId)
            : false;

        if (! $rallyRosterPresence
            || $alliance->kingdomId !== $player->kingdomId
            || ! $this->participants->eligibleAgainstTarget($target, $player, $eventRosterPresence)) {
            throw ValidationException::withMessages([
                'player' => 'This Player is not currently eligible for this Rally Alliance.',
            ]);
        }

        return $player;
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Rally write state must be acquired inside a database transaction.');
        }
    }
}
