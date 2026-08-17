<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Queries;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use Illuminate\Support\Collection;

final readonly class EventEligiblePlayerQuery
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AllianceReferenceQuery $alliances,
        private RosterEntryQuery $roster,
    ) {}

    /** @return Collection<int, PlayerReference> */
    public function for(Event $event): Collection
    {
        return match ($event->scopeEnum()) {
            EventScope::Player => $this->player((string) $event->player_id),
            EventScope::Kingdom => collect($this->players->inKingdom((string) $event->kingdom_id)),
            EventScope::Alliance => $this->alliancePlayers((string) $event->alliance_id),
        };
    }

    /** @return Collection<int, PlayerReference> */
    private function player(string $playerId): Collection
    {
        if ($playerId === '') {
            return collect();
        }

        $player = $this->players->find($playerId);

        return $player instanceof PlayerReference ? collect([$player]) : collect();
    }

    /** @return Collection<int, PlayerReference> */
    private function alliancePlayers(string $allianceId): Collection
    {
        if ($allianceId === '') {
            return collect();
        }

        $alliance = $this->alliances->find($allianceId);
        if ($alliance === null || ! $alliance->active()) {
            return collect();
        }

        $references = $this->players->byIds($this->roster->activePlayerIds($allianceId));

        return collect($references)
            ->filter(static fn (PlayerReference $player): bool => $player->kingdomId === $alliance->kingdomId)
            ->sortBy(static fn (PlayerReference $player): string => mb_strtolower($player->currentName))
            ->values();
    }
}
