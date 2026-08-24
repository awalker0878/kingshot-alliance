<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Queries;

use App\Contexts\Operations\BattlePlans\Enums\EventObjectiveStatus;
use App\Contexts\Operations\BattlePlans\Models\EventObjective;
use App\Contexts\Operations\BattlePlans\Models\EventObjectiveAssignment;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;

final readonly class EventBattlePlanCommandQuery
{
    /**
     * @return array{
     *   objectiveCount:int,
     *   assignmentCount:int,
     *   plannedPlayerCount:int,
     *   assignedPlayerCount:int,
     *   unassignedPlayerCount:int,
     *   invalidAssignmentCount:int
     * }
     */
    public function forOccurrence(EventOccurrence $occurrence): array
    {
        $objectives = EventObjective::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('status', '!=', EventObjectiveStatus::Cancelled->value)
            ->with('assignments')
            ->get();
        $plannedPlayerIds = EventRosterMember::query()
            ->whereNotIn('status', [EventRosterMemberStatus::Declined->value, EventRosterMemberStatus::Removed->value])
            ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values();
        $assignments = $objectives
            ->flatMap(static fn (EventObjective $objective) => $objective->assignments)
            ->filter(static fn ($assignment): bool => $assignment instanceof EventObjectiveAssignment)
            ->values();
        $rosterIds = $assignments
            ->map(static fn (EventObjectiveAssignment $assignment): ?string => is_string($assignment->roster_id) && $assignment->roster_id !== '' ? $assignment->roster_id : null)
            ->filter()
            ->unique()
            ->values();
        $rosterPlayers = $rosterIds->isEmpty()
            ? collect()
            : EventRosterMember::query()
                ->whereIn('roster_id', $rosterIds->all())
                ->whereNotIn('status', [EventRosterMemberStatus::Declined->value, EventRosterMemberStatus::Removed->value])
                ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
                ->get(['roster_id', 'player_id'])
                ->groupBy('roster_id');
        $assignedPlayerIds = collect();
        $invalidAssignmentCount = 0;

        foreach ($assignments as $assignment) {
            if (is_string($assignment->player_id) && $assignment->player_id !== '') {
                $assignedPlayerIds->push($assignment->player_id);
                continue;
            }

            if (is_string($assignment->roster_id) && $assignment->roster_id !== '') {
                $players = $rosterPlayers->get($assignment->roster_id, collect());
                if ($players->isEmpty()) {
                    $invalidAssignmentCount++;
                }
                foreach ($players as $member) {
                    $assignedPlayerIds->push((string) $member->player_id);
                }
                continue;
            }

            $invalidAssignmentCount++;
        }

        $assignedPlayerIds = $assignedPlayerIds->unique()->values();

        return [
            'objectiveCount' => $objectives->count(),
            'assignmentCount' => $assignments->count(),
            'plannedPlayerCount' => $plannedPlayerIds->count(),
            'assignedPlayerCount' => $plannedPlayerIds->intersect($assignedPlayerIds)->count(),
            'unassignedPlayerCount' => $plannedPlayerIds->diff($assignedPlayerIds)->count(),
            'invalidAssignmentCount' => $invalidAssignmentCount,
        ];
    }
}
