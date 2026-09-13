<?php

declare(strict_types=1);

namespace App\ReadModels\EventAnalysis\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Queries\EventEligiblePlayerQuery;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Results\Services\ResultScoreTotal;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class EventPlayerIntelligenceQuery
{
    public function __construct(private EventEligiblePlayerQuery $eligiblePlayers) {}

    /** @return list<array<string,mixed>> */
    public function forEvent(Event $event): array
    {
        $players = $this->eligiblePlayers->for($event)->values();
        $metrics = $this->calculate(
            $players,
            $this->historicalOccurrenceIds($event),
            $this->historicalOccurrenceIds($event, comparableScoresOnly: true),
        );

        return array_values($players->map(fn (PlayerReference $player): array => $metrics[$player->playerId])->all());
    }

    /** @return array<string,mixed> */
    public function forPlayer(Event $event, PlayerReference $player): array
    {
        return $this->calculate(
            collect([$player]),
            $this->historicalOccurrenceIds($event),
            $this->historicalOccurrenceIds($event, comparableScoresOnly: true),
        )[$player->playerId];
    }

    /** @return Builder<EventOccurrence> */
    private function historicalOccurrenceIds(Event $event, bool $comparableScoresOnly = false): Builder
    {
        $eventIds = Event::query()
            ->where('scope', $event->scope->value)
            ->when($event->player_id !== null, static fn ($q) => $q->where('player_id', $event->player_id))
            ->when($event->alliance_id !== null, static fn ($q) => $q->where('alliance_id', $event->alliance_id))
            ->when($event->kingdom_id !== null, static fn ($q) => $q->where('kingdom_id', $event->kingdom_id))
            ->when($comparableScoresOnly, static fn ($q) => $q->where('event_type_scope_id', $event->event_type_scope_id))
            ->select('id');

        return EventOccurrence::query()->select('id')
            ->whereIn('event_id', $eventIds)->where('ends_at', '<=', now());
    }

    /**
     * @param  Collection<int,PlayerReference>  $players
     * @param  Builder<EventOccurrence>  $occurrenceIds
     * @param  Builder<EventOccurrence>  $scoreOccurrenceIds
     * @return array<string,array<string,mixed>>
     */
    private function calculate(Collection $players, Builder $occurrenceIds, Builder $scoreOccurrenceIds): array
    {
        $result = [];
        foreach ($players as $player) {
            $result[$player->playerId] = $this->empty($player);
        }
        if ($players->isEmpty()) {
            return $result;
        }

        $playerIds = $players->pluck('playerId')->all();
        $registrations = DB::table('event_registrations')->whereIn('player_id', $playerIds)
            ->whereIn('occurrence_id', clone $occurrenceIds)->where('status', EventRegistrationStatus::Registered->value)
            ->selectRaw('player_id, occurrence_id, 1 AS committed, 0 AS completed, 0 AS absent, 0 AS excused');
        $rosters = DB::table('event_roster_members as member')->join('event_rosters as roster', 'roster.id', '=', 'member.roster_id')
            ->whereIn('member.player_id', $playerIds)->whereIn('roster.occurrence_id', clone $occurrenceIds)
            ->whereIn('member.status', [EventRosterMemberStatus::Confirmed->value, EventRosterMemberStatus::Participated->value, EventRosterMemberStatus::Absent->value])
            ->selectRaw('member.player_id, roster.occurrence_id, 1 AS committed, CASE WHEN member.status = ? THEN 1 ELSE 0 END AS completed, CASE WHEN member.status = ? THEN 1 ELSE 0 END AS absent, 0 AS excused', [EventRosterMemberStatus::Participated->value, EventRosterMemberStatus::Absent->value]);
        $rallies = DB::table('rally_assignments as assignment')->join('rally_groups as rally', 'rally.id', '=', 'assignment.rally_group_id')
            ->whereIn('assignment.player_id', $playerIds)->whereIn('rally.occurrence_id', clone $occurrenceIds)
            ->whereIn('assignment.status', [RallyAssignmentStatus::Confirmed->value, RallyAssignmentStatus::Participated->value, RallyAssignmentStatus::Absent->value])
            ->selectRaw('assignment.player_id, rally.occurrence_id, 1 AS committed, CASE WHEN assignment.status = ? THEN 1 ELSE 0 END AS completed, CASE WHEN assignment.status = ? THEN 1 ELSE 0 END AS absent, 0 AS excused', [RallyAssignmentStatus::Participated->value, RallyAssignmentStatus::Absent->value]);
        $attendance = DB::table('event_attendance')->whereIn('player_id', $playerIds)->whereIn('occurrence_id', clone $occurrenceIds)
            ->selectRaw('player_id, occurrence_id, 0 AS committed, CASE WHEN status = ? THEN 1 ELSE 0 END AS completed, CASE WHEN status = ? THEN 1 ELSE 0 END AS absent, CASE WHEN status = ? THEN 1 ELSE 0 END AS excused', [EventAttendanceStatus::Present->value, EventAttendanceStatus::Absent->value, EventAttendanceStatus::Excused->value]);
        // One fact per Governor/occurrence preserves set union and resolution priority.
        $facts = DB::query()->fromSub($registrations->unionAll($rosters)->unionAll($rallies)->unionAll($attendance), 'fact')
            ->selectRaw('player_id, occurrence_id, MAX(committed) AS committed, MAX(completed) AS completed, MAX(absent) AS absent, MAX(excused) AS excused')
            ->groupBy('player_id', 'occurrence_id');
        $counts = DB::query()->fromSub($facts, 'resolved')->selectRaw('player_id, SUM(committed) AS commitments, SUM(completed) AS completed, SUM(CASE WHEN completed = 0 AND excused = 1 THEN 1 ELSE 0 END) AS excused, SUM(CASE WHEN completed = 0 AND excused = 0 AND absent = 1 THEN 1 ELSE 0 END) AS absent, SUM(CASE WHEN committed = 1 AND completed = 0 AND excused = 0 AND absent = 0 THEN 1 ELSE 0 END) AS unresolved')
            ->groupBy('player_id')->get()->keyBy('player_id');
        $scoreQuery = DB::table('event_player_results')->whereIn('player_id', $playerIds)
            ->whereIn('occurrence_id', $scoreOccurrenceIds)->whereNotNull('score');
        $scores = (clone $scoreQuery)->selectRaw('player_id, COUNT(*) AS result_count, ROUND(AVG(score)) AS average_score, MAX(score) AS best_score')
            ->groupBy('player_id')->get()->keyBy('player_id');
        $latest = $scoreQuery->selectRaw('DISTINCT ON (player_id) player_id, score')->orderBy('player_id')
            ->orderByDesc('recorded_at')->orderByDesc('id')->get()->keyBy('player_id');
        foreach ($players as $player) {
            $id = $player->playerId;
            $row = $counts->get($id);
            $completed = (int) ($row->completed ?? 0);
            $absent = (int) ($row->absent ?? 0);
            $score = $scores->get($id);
            $result[$id] = ['playerId' => $id, 'playerName' => $player->currentName,
                'commitments' => (int) ($row->commitments ?? 0), 'completed' => $completed,
                'absent' => $absent, 'excused' => (int) ($row->excused ?? 0), 'unresolved' => (int) ($row->unresolved ?? 0),
                'reliabilityPercent' => $completed + $absent === 0 ? null : round(($completed / ($completed + $absent)) * 100, 1),
                'resultCount' => (int) ($score->result_count ?? 0),
                'averageScore' => ResultScoreTotal::fromDatabase($score->average_score ?? null),
                'bestScore' => ResultScoreTotal::fromDatabase($score->best_score ?? null),
                'latestScore' => ResultScoreTotal::fromDatabase($latest->get($id)->score ?? null)];
        }

        return $result;
    }

    /** @return array<string,mixed> */
    private function empty(PlayerReference $player): array
    {
        return [
            'playerId' => $player->playerId,
            'playerName' => $player->currentName,
            'commitments' => 0,
            'completed' => 0,
            'absent' => 0,
            'excused' => 0,
            'unresolved' => 0,
            'reliabilityPercent' => null,
            'resultCount' => 0,
            'averageScore' => null,
            'bestScore' => null,
            'latestScore' => null,
        ];
    }
}
