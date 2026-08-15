<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceDashboard;

use App\Contexts\Alliance\Core\Models\Alliance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class UpcomingAllianceActivitiesQuery
{
    /** @return list<array{id: string, title: string, startsAt: string, allianceTimezone: string}> */
    public function handle(Alliance $alliance, int $limit = 5): array
    {
        return array_values(DB::table('event_occurrences')
            ->join('events', 'events.id', '=', 'event_occurrences.event_id')
            ->where('events.scope', 'alliance')
            ->where('events.alliance_id', $alliance->id)
            ->where('event_occurrences.status', 'scheduled')
            ->whereBetween('event_occurrences.starts_at', [now(), now()->addDays(30)])
            ->orderBy('event_occurrences.starts_at')
            ->limit(max(1, min($limit, 20)))
            ->get(['event_occurrences.id', 'events.title', 'event_occurrences.starts_at', 'events.timezone'])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'title' => (string) $row->title,
                'startsAt' => Carbon::parse((string) $row->starts_at)->toIso8601String(),
                'allianceTimezone' => (string) $row->timezone,
            ])->values()->all());
    }
}
