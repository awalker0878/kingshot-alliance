<?php

declare(strict_types=1);

namespace App\Domain\Events\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Collection;

final class AllianceEventQuery
{
    /** @return Collection<int, EventOccurrence> */
    public function calendar(Alliance $alliance, int $pastDays = 7, int $futureDays = 90): Collection
    {
        $pastDays = max(0, min($pastDays, 31));
        $futureDays = max(1, min($futureDays, 366));

        return EventOccurrence::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', EventOccurrenceStatus::Scheduled->value)
            ->whereBetween('starts_at', [
                now()->subDays($pastDays),
                now()->addDays($futureDays),
            ])
            ->with('event')
            ->orderBy('starts_at')
            ->limit(500)
            ->get();
    }

    public function occurrence(Alliance $alliance, string $occurrenceId): EventOccurrence
    {
        return EventOccurrence::query()
            ->whereKey($occurrenceId)
            ->where('alliance_id', $alliance->id)
            ->with('event')
            ->firstOrFail();
    }
}
