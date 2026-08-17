<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\EventAnalysis\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Participation\Queries\EventEligiblePlayerQuery;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use Illuminate\Support\Collection;

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

    /** @return Collection<int,string> */
    private function historicalOccurrenceIds(Event $event, bool $comparableScoresOnly = false): Collection
    {
        $eventIds = Event::query()
            ->where('scope', $event->scope->value)
            ->when($event->player_id !== null, static fn ($q) => $q->where('player_id', $event->player_id))
            ->when($event->alliance_id !== null, static fn ($q) => $q->where('alliance_id', $event->alliance_id))
            ->when($event->kingdom_id !== null, static fn ($q) => $q->where('kingdom_id', $event->kingdom_id))
            ->when($comparableScoresOnly, static fn ($q) => $q->where('event_type_scope_id', $event->event_type_scope_id))
            ->pluck('id');

        return EventOccurrence::query()
            ->whereIn('event_id', $eventIds)
            ->where('ends_at', '<=', now())
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id);
    }

    /**
     * @param  Collection<int,PlayerReference>  $players
     * @param  Collection<int,string>  $occurrenceIds
     * @param  Collection<int,string>  $scoreOccurrenceIds
     * @return array<string,array<string,mixed>>
     */
    private function calculate(Collection $players, Collection $occurrenceIds, Collection $scoreOccurrenceIds): array
    {
        $result = [];
        foreach ($players as $player) {
            $result[$player->playerId] = $this->empty($player);
        }
        if ($players->isEmpty() || $occurrenceIds->isEmpty()) {
            return $result;
        }

        $playerIds = $players->pluck('playerId')->map(static fn ($id): string => (string) $id);
        $registrations = EventRegistration::query()
            ->whereIn('player_id', $playerIds)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->where('status', EventRegistrationStatus::Registered->value)
            ->get(['player_id', 'occurrence_id'])
            ->groupBy('player_id');
        $rosters = EventRosterMember::query()
            ->whereIn('player_id', $playerIds)
            ->whereIn('status', [EventRosterMemberStatus::Confirmed->value, EventRosterMemberStatus::Participated->value, EventRosterMemberStatus::Absent->value])
            ->whereHas('roster', static fn ($q) => $q->whereIn('occurrence_id', $occurrenceIds))
            ->with('roster:id,occurrence_id')
            ->get()
            ->groupBy('player_id');
        $rallies = RallyAssignment::query()
            ->whereIn('player_id', $playerIds)
            ->whereIn('status', [RallyAssignmentStatus::Confirmed->value, RallyAssignmentStatus::Participated->value, RallyAssignmentStatus::Absent->value])
            ->whereHas('rallyGroup', static fn ($q) => $q->whereIn('occurrence_id', $occurrenceIds))
            ->with('rallyGroup:id,occurrence_id')
            ->get()
            ->groupBy('player_id');
        $attendance = EventAttendance::query()
            ->whereIn('player_id', $playerIds)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->get()
            ->groupBy('player_id');
        $scores = EventPlayerResult::query()
            ->whereIn('player_id', $playerIds)
            ->whereIn('occurrence_id', $scoreOccurrenceIds)
            ->whereNotNull('score')
            ->orderByDesc('recorded_at')
            ->get(['player_id', 'score', 'recorded_at'])
            ->groupBy('player_id');

        foreach ($players as $player) {
            $id = $player->playerId;
            $playerRegistrations = $registrations->get($id, collect());
            $playerRosters = $rosters->get($id, collect());
            $playerRallies = $rallies->get($id, collect());
            $playerAttendance = $attendance->get($id, collect());

            $committed = $playerRegistrations->pluck('occurrence_id')->map(static fn ($id): string => (string) $id)
                ->merge($playerRosters->pluck('roster.occurrence_id')->filter()->map(static fn ($id): string => (string) $id))
                ->merge($playerRallies->pluck('rallyGroup.occurrence_id')->filter()->map(static fn ($id): string => (string) $id))
                ->unique();
            $completed = $playerAttendance->where('status', EventAttendanceStatus::Present)->pluck('occurrence_id')->map(static fn ($id): string => (string) $id)
                ->merge($playerRosters->where('status', EventRosterMemberStatus::Participated)->pluck('roster.occurrence_id')->filter()->map(static fn ($id): string => (string) $id))
                ->merge($playerRallies->where('status', RallyAssignmentStatus::Participated)->pluck('rallyGroup.occurrence_id')->filter()->map(static fn ($id): string => (string) $id))
                ->unique();
            $excused = $playerAttendance->where('status', EventAttendanceStatus::Excused)->pluck('occurrence_id')->map(static fn ($id): string => (string) $id)->unique()->diff($completed);
            $missed = $playerAttendance->where('status', EventAttendanceStatus::Absent)->pluck('occurrence_id')->map(static fn ($id): string => (string) $id)
                ->merge($playerRosters->where('status', EventRosterMemberStatus::Absent)->pluck('roster.occurrence_id')->filter()->map(static fn ($id): string => (string) $id))
                ->merge($playerRallies->where('status', RallyAssignmentStatus::Absent)->pluck('rallyGroup.occurrence_id')->filter()->map(static fn ($id): string => (string) $id))
                ->unique()->diff($completed)->diff($excused);
            $resolved = $completed->merge($missed)->merge($excused)->unique();
            $unresolved = $committed->diff($resolved)->unique();
            $denominator = $completed->count() + $missed->count();
            $playerScores = $scores->get($id, collect())->pluck('score')->map(static fn ($score): int => (int) $score);
            $averageScore = $playerScores->avg();

            $result[$id] = [
                'playerId' => $id,
                'playerName' => $player->currentName,
                'commitments' => $committed->count(),
                'completed' => $completed->count(),
                'absent' => $missed->count(),
                'excused' => $excused->count(),
                'unresolved' => $unresolved->count(),
                'reliabilityPercent' => $denominator === 0 ? null : round(($completed->count() / $denominator) * 100, 1),
                'resultCount' => $playerScores->count(),
                'averageScore' => $averageScore === null ? null : (int) round((float) $averageScore),
                'bestScore' => $playerScores->isEmpty() ? null : (int) $playerScores->max(),
                'latestScore' => $playerScores->first(),
            ];
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
