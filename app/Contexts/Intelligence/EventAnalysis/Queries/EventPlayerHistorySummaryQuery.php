<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\EventAnalysis\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class EventPlayerHistorySummaryQuery
{
    public function __construct(private EventPlayerOccurrenceEvidenceQuery $evidence) {}

    /**
     * Lifetime Event summary for one durable Player.
     *
     * Current Alliance membership and current Kingdom placement are deliberately
     * absent from this query. Historical ownership comes from Event target and
     * frozen occurrence context only.
     *
     * @return array{
     *   events:int,
     *   player_events:int,
     *   alliance_events:int,
     *   kingdom_events:int,
     *   completed:int,
     *   absent:int,
     *   excused:int,
     *   unresolved:int,
     *   reliability_percent:?float
     * }
     */
    public function forPlayer(PlayerReference $player): array
    {
        $base = DB::table('event_player_contexts as context')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'context.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->where('context.player_id', $player->playerId);

        $scopes = (clone $base)
            ->select('event.scope', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('event.scope')
            ->pluck('aggregate', 'event.scope');

        $completed = $this->outcomeCount($base, $player, 'completed');
        $absent = $this->outcomeCount($base, $player, 'absent');
        $excused = $this->outcomeCount($base, $player, 'excused');
        $unresolved = $this->outcomeCount($base, $player, 'unresolved');
        $decided = $completed + $absent;

        return [
            'events' => (clone $base)->count(),
            'player_events' => (int) ($scopes[EventScope::Player->value] ?? 0),
            'alliance_events' => (int) ($scopes[EventScope::Alliance->value] ?? 0),
            'kingdom_events' => (int) ($scopes[EventScope::Kingdom->value] ?? 0),
            'completed' => $completed,
            'absent' => $absent,
            'excused' => $excused,
            'unresolved' => $unresolved,
            'reliability_percent' => $decided === 0
                ? null
                : round(($completed / $decided) * 100, 2),
        ];
    }

    private function outcomeCount(Builder $base, PlayerReference $player, string $outcome): int
    {
        $query = clone $base;
        $this->evidence->applyOutcomeFilter($query, $player, $outcome);

        return $query->count();
    }
}
