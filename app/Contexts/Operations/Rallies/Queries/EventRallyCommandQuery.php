<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Queries;

use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;

final readonly class EventRallyCommandQuery
{
    /**
     * @return array{
     *     groupCount:int,
     *     plannedAssignmentCount:int,
     *     leadCount:int,
     *     joinerCount:int,
     *     groupsWithoutLeadCount:int,
     *     declinedCount:int,
     *     recordedActualCount:int,
     *     missingActualCount:int
     * }
     */
    public function forOccurrence(EventOccurrence $occurrence): array
    {
        $groups = RallyGroup::query()
            ->where('occurrence_id', $occurrence->id)
            ->with('assignments')
            ->get();
        $plannedAssignmentCount = 0;
        $leadCount = 0;
        $joinerCount = 0;
        $groupsWithoutLeadCount = 0;
        $declinedCount = 0;
        $recordedActualCount = 0;
        $missingActualCount = 0;

        foreach ($groups as $group) {
            if (! $group instanceof RallyGroup) {
                continue;
            }

            $active = $group->assignments->filter(
                static fn (RallyAssignment $assignment): bool => $assignment->status->occupiesAssignment(),
            );
            $activeLeads = $active->filter(
                static fn (RallyAssignment $assignment): bool => $assignment->role === RallyAssignmentRole::Lead,
            );
            if ($activeLeads->isEmpty()) {
                $groupsWithoutLeadCount++;
            }

            $plannedAssignmentCount += $active->count();
            $leadCount += $activeLeads->count();
            $joinerCount += $active->filter(
                static fn (RallyAssignment $assignment): bool => $assignment->role === RallyAssignmentRole::Joiner,
            )->count();
            $declinedCount += $group->assignments->filter(
                static fn (RallyAssignment $assignment): bool => $assignment->status === RallyAssignmentStatus::Declined,
            )->count();

            foreach ($active as $assignment) {
                if (
                    $assignment->recorded_at !== null
                    || in_array(
                        $assignment->status,
                        [RallyAssignmentStatus::Participated, RallyAssignmentStatus::Absent],
                        true,
                    )
                ) {
                    $recordedActualCount++;
                } else {
                    $missingActualCount++;
                }
            }
        }

        return [
            'groupCount' => $groups->count(),
            'plannedAssignmentCount' => $plannedAssignmentCount,
            'leadCount' => $leadCount,
            'joinerCount' => $joinerCount,
            'groupsWithoutLeadCount' => $groupsWithoutLeadCount,
            'declinedCount' => $declinedCount,
            'recordedActualCount' => $recordedActualCount,
            'missingActualCount' => $missingActualCount,
        ];
    }
}
