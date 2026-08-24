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
        $assignedPlayerIds = collect();
        $assignmentCount = 0;
        $invalidAssignmentCount = 0;

        foreach ($objectives as $objective) {
            if (! $objective instanceof EventObjective) {
                continue;
            }

            foreach ($objective->assignments as $assignment) {
                if (! $assignment instanceof EventObjectiveAssignment) {
                    continue;
                }
                $assignmentCount++;

                if (is_string($assignment->player_id) && $assignment->player_id !== '') {
                    $assignedPlayerIds->push($assignment->player_id);
                    continue;
                }

                if (is_string($assignment->roster_id) && $assignment->roster_id !== '') {
                    $rosterPlayers = EventRosterMember::query()
                        ->where('roster_id', $assignment->roster_id)
                        ->whereNotIn('status', [EventRosterMemberStatus::Declined->value, EventRosterMemberStatus::Removed->value])
                        ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
                        ->pluck('player_id')
                        ->map(static fn ($id): string => (string) $id);
                    if ($rosterPlayers->isEmpty()) {
                        $invalidAssignmentCount++;
                    }
                    $assignedPlayerIds = $assignedPlayerIds->merge($rosterPlayers);
                    continue;
                }

                $invalidAssignmentCount++;
            }
        }

        $assignedPlayerIds = $assignedPlayerIds->unique()->values();
        $unassigned = $plannedPlayerIds->diff($assignedPlayerIds)->values();

        return [
            'objectiveCount' => $objectives->count(),
            'assignmentCount' => $assignmentCount,
            'plannedPlayerCount' => $plannedPlayerIds->count(),
            'assignedPlayerCount' => $plannedPlayerIds->intersect($assignedPlayerIds)->count(),
            'unassignedPlayerCount' => $unassigned->count(),
            'invalidAssignmentCount' => $invalidAssignmentCount,
        ];
    }
}
