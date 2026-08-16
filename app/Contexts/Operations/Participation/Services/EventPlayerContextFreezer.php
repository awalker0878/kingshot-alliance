<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Services;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Models\EventPlayerContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class EventPlayerContextFreezer
{
    public function existing(EventOccurrence $occurrence, Player $player): ?EventPlayerContext
    {
        $this->assertTransaction();

        return EventPlayerContext::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('player_id', $player->id)
            ->lockForUpdate()
            ->first();
    }

    public function freeze(
        EventOccurrence $occurrence,
        Player $player,
        ?Alliance $representedAlliance = null,
    ): EventPlayerContext {
        $existing = $this->existing($occurrence, $player);
        if ($existing instanceof EventPlayerContext) {
            return $existing;
        }

        $event = $occurrence->event()->firstOrFail();
        $scope = $event->scopeEnum();
        $kingdomId = match ($scope) {
            EventScope::Player => (string) $player->current_kingdom_id,
            EventScope::Alliance => $this->allianceKingdom($event->alliance_id, $player, $representedAlliance),
            EventScope::Kingdom => $this->kingdomEventKingdom($event->kingdom_id, $player),
        };

        $alliance = match ($scope) {
            EventScope::Alliance => $this->allianceForAllianceEvent($event->alliance_id, $representedAlliance),
            EventScope::Kingdom, EventScope::Player => $this->representedAlliance($player, $kingdomId, $representedAlliance),
        };

        return EventPlayerContext::query()->create([
            'occurrence_id' => $occurrence->id,
            'player_id' => $player->id,
            'player_name_snapshot' => (string) $player->current_name,
            'represented_alliance_id' => $alliance?->id,
            'represented_alliance_name_snapshot' => $alliance?->name,
            'represented_alliance_tag_snapshot' => null,
            'kingdom_id_at_event' => $kingdomId,
            'context_frozen_at' => now(),
        ]);
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Event Player context must be read or frozen inside a database transaction.');
        }
    }

    private function allianceKingdom(?string $allianceId, Player $player, ?Alliance $representedAlliance): string
    {
        $alliance = $this->allianceForAllianceEvent($allianceId, $representedAlliance);

        if ((string) $player->current_kingdom_id !== (string) $alliance->kingdom_id) {
            throw ValidationException::withMessages([
                'player' => 'The Player is no longer in the Alliance Event Kingdom.',
            ]);
        }

        return (string) $alliance->kingdom_id;
    }

    private function kingdomEventKingdom(?string $kingdomId, Player $player): string
    {
        if ($kingdomId === null || (string) $player->current_kingdom_id !== (string) $kingdomId) {
            throw ValidationException::withMessages([
                'player' => 'The Player is no longer in the Kingdom Event target.',
            ]);
        }

        return (string) $kingdomId;
    }

    private function allianceForAllianceEvent(?string $allianceId, ?Alliance $representedAlliance): Alliance
    {
        if ($allianceId === null) {
            throw new LogicException('Alliance Event context requires an Alliance target.');
        }

        if ($representedAlliance instanceof Alliance) {
            if ((string) $representedAlliance->id !== (string) $allianceId) {
                throw ValidationException::withMessages([
                    'represented_alliance_id' => 'Represented Alliance does not match the Alliance Event target.',
                ]);
            }

            return $representedAlliance;
        }

        return Alliance::query()->whereKey($allianceId)->firstOrFail();
    }

    private function representedAlliance(
        Player $player,
        string $kingdomId,
        ?Alliance $representedAlliance,
    ): ?Alliance {
        if ($representedAlliance instanceof Alliance) {
            if ((string) $representedAlliance->kingdom_id !== $kingdomId) {
                throw ValidationException::withMessages([
                    'represented_alliance_id' => 'Represented Alliance must belong to the Player Kingdom at the Event.',
                ]);
            }

            return $representedAlliance;
        }

        $memberships = AllianceMembership::query()
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('alliance', static fn ($query) => $query->where('kingdom_id', $kingdomId))
            ->with('alliance')
            ->orderBy('id')
            ->get();

        if ($memberships->count() !== 1) {
            return null;
        }

        $alliance = $memberships->first()?->alliance;

        return $alliance instanceof Alliance ? $alliance : null;
    }
}
