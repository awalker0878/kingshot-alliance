<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Services;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Models\EventPlayerContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class EventPlayerContextFreezer
{
    public function __construct(
        private AllianceReferenceQuery $alliances,
        private PlayerMembershipQuery $memberships,
    ) {}

    public function existing(string $occurrenceId, string $playerId): ?EventPlayerContext
    {
        $this->assertTransaction();

        return EventPlayerContext::query()
            ->where('occurrence_id', $occurrenceId)
            ->where('player_id', $playerId)
            ->lockForUpdate()
            ->first();
    }

    public function freeze(
        EventOccurrence $occurrence,
        PlayerReference $player,
        ?string $representedAllianceId = null,
    ): EventPlayerContext {
        $existing = $this->existing((string) $occurrence->id, $player->playerId);
        if ($existing instanceof EventPlayerContext) {
            return $existing;
        }

        $event = $occurrence->event()->firstOrFail();
        $scope = $event->scopeEnum();
        $kingdomId = match ($scope) {
            EventScope::Player => $player->kingdomId,
            EventScope::Alliance => $this->allianceEventKingdom((string) $event->alliance_id, $player),
            EventScope::Kingdom => $this->kingdomEventKingdom((string) $event->kingdom_id, $player),
        };

        $alliance = match ($scope) {
            EventScope::Alliance => $this->requireRepresentedAlliance((string) $event->alliance_id, $kingdomId),
            EventScope::Kingdom, EventScope::Player => $this->representedAlliance($player, $kingdomId, $representedAllianceId),
        };

        return EventPlayerContext::query()->create([
            'occurrence_id' => $occurrence->id,
            'player_id' => $player->playerId,
            'player_name_snapshot' => $player->currentName,
            'represented_alliance_id' => $alliance?->allianceId,
            'represented_alliance_name_snapshot' => $alliance?->name,
            'represented_alliance_tag_snapshot' => null,
            'kingdom_id_at_event' => $kingdomId,
            'context_frozen_at' => now(),
        ]);
    }

    private function allianceEventKingdom(string $allianceId, PlayerReference $player): string
    {
        if ($allianceId === '') {
            throw new LogicException('Alliance Event context requires an Alliance target.');
        }

        $alliance = $this->alliances->lockCurrent($allianceId);
        if ($player->kingdomId !== $alliance->kingdomId) {
            throw ValidationException::withMessages([
                'player' => 'The Player is no longer in the Alliance Event Kingdom.',
            ]);
        }

        return $alliance->kingdomId;
    }

    private function kingdomEventKingdom(string $kingdomId, PlayerReference $player): string
    {
        if ($kingdomId === '' || $player->kingdomId !== $kingdomId) {
            throw ValidationException::withMessages([
                'player' => 'The Player is no longer in the Kingdom Event target.',
            ]);
        }

        return $kingdomId;
    }

    private function requireRepresentedAlliance(string $allianceId, string $kingdomId): AllianceReference
    {
        $alliance = $this->alliances->lockCurrent($allianceId);
        if ($alliance->kingdomId !== $kingdomId) {
            throw ValidationException::withMessages([
                'represented_alliance_id' => 'Represented Alliance does not match the Event Kingdom.',
            ]);
        }

        return $alliance;
    }

    private function representedAlliance(
        PlayerReference $player,
        string $kingdomId,
        ?string $representedAllianceId,
    ): ?AllianceReference {
        if ($representedAllianceId !== null) {
            return $this->requireRepresentedAlliance($representedAllianceId, $kingdomId);
        }

        $allianceIds = $this->memberships->activeAllianceIdsForPlayerInKingdom($player->playerId, $kingdomId);
        if (count($allianceIds) !== 1) {
            return null;
        }

        return $this->alliances->lockCurrent($allianceIds[0]);
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Event Player context must be read or frozen inside a database transaction.');
        }
    }
}
