<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Services;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final readonly class RallyAllianceResolver
{
    public function __construct(
        private AllianceReferenceQuery $alliances,
        private RosterEntryQuery $roster,
        private PlayerReferenceQuery $players,
    ) {}

    /** @return Collection<int, AllianceReference> */
    public function forEvent(Event $event): Collection
    {
        return match ($event->scopeEnum()) {
            EventScope::Alliance => $this->allianceEvent((string) $event->alliance_id),
            EventScope::Kingdom => collect($this->alliances->inKingdom((string) $event->kingdom_id, activeOnly: true)),
            EventScope::Player => $this->playerEvent($event),
        };
    }

    public function resolve(Event $event, string $allianceId): AllianceReference
    {
        $alliance = $this->forEvent($event)->first(
            static fn (AllianceReference $candidate): bool => $candidate->allianceId === $allianceId,
        );

        if (! $alliance instanceof AllianceReference) {
            throw ValidationException::withMessages([
                'alliance_id' => 'This Alliance is not a valid Rally context for the Event.',
            ]);
        }

        return $alliance;
    }

    /** @return Collection<int, AllianceReference> */
    private function allianceEvent(string $allianceId): Collection
    {
        $alliance = $allianceId === '' ? null : $this->alliances->find($allianceId);

        return $alliance instanceof AllianceReference && $alliance->active()
            ? collect([$alliance])
            : collect();
    }

    /** @return Collection<int, AllianceReference> */
    private function playerEvent(Event $event): Collection
    {
        if ($event->player_id === null) {
            return collect();
        }

        $player = $this->players->require((string) $event->player_id);
        $allianceIds = $this->roster->activeAllianceIdsForPlayerInKingdom($player->playerId, $player->kingdomId);
        $alliances = $this->alliances->byIds($allianceIds);

        return collect($allianceIds)
            ->map(static fn (string $allianceId): ?AllianceReference => $alliances[$allianceId] ?? null)
            ->filter(static fn (?AllianceReference $alliance): bool => $alliance instanceof AllianceReference && $alliance->active())
            ->values();
    }
}
