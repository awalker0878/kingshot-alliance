<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Services;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class RallyAllianceResolver
{
    /** @return Collection<int,Alliance> */
    public function forEvent(Event $event): Collection
    {
        return match ($event->scope) {
            EventScope::Alliance => Alliance::query()->whereKey($event->alliance_id)->where('status', AllianceStatus::Active->value)->get(),
            EventScope::Kingdom => Alliance::query()->where('kingdom_id', $event->kingdom_id)->where('status', AllianceStatus::Active->value)->orderBy('name')->get(),
            EventScope::Player => $this->forPlayerEvent($event),
        };
    }

    public function resolve(Event $event, string $allianceId): Alliance
    {
        $alliance = $this->forEvent($event)->first(static fn (Alliance $candidate): bool => (string) $candidate->id === $allianceId);
        if (! $alliance instanceof Alliance) {
            throw ValidationException::withMessages(['alliance_id' => 'This Alliance is not a valid Rally context for the Event.']);
        }

        return $alliance;
    }

    /** @return Collection<int,Alliance> */
    private function forPlayerEvent(Event $event): Collection
    {
        if ($event->player_id === null) {
            return collect();
        }

        $ids = AllianceRosterEntry::query()
            ->where('player_id', $event->player_id)
            ->where('state', RosterState::Active->value)
            ->pluck('alliance_id');

        return Alliance::query()
            ->whereIn('id', $ids)
            ->where('kingdom_id', $event->player()->value('current_kingdom_id'))
            ->where('status', AllianceStatus::Active->value)
            ->orderBy('name')
            ->get();
    }
}
