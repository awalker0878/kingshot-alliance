<?php

declare(strict_types=1);

namespace App\ReadModels\EventAnalysis\Queries;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EventOrganizationEvidenceQuery
{
    /**
     * Summarize immutable historical operational evidence for organization Event history.
     *
     * The query is occurrence-scoped only. It deliberately does not consult current
     * Alliance membership or current Kingdom placement when describing past Events.
     *
     * @param  Collection<int, string>  $occurrenceIds
     * @return array<string,array{
     *   attendance:array{total:int,byStatus:array<string,int>},
     *   roster:array{total:int,byStatus:array<string,int>},
     *   rallies:array{total:int,byStatus:array<string,int>},
     *   objectives:array{total:int,assignments:int,byStatus:array<string,int>}
     * }>
     */
    public function forOccurrences(Collection $occurrenceIds): array
    {
        $ids = $occurrenceIds->map(static fn ($id): string => (string) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $attendance = $this->statusCounts(
            DB::table('event_attendance')
                ->whereIn('occurrence_id', $ids)
                ->select('occurrence_id', 'status', DB::raw('COUNT(*) AS aggregate'))
                ->groupBy('occurrence_id', 'status')
                ->get(),
        );

        $roster = $this->statusCounts(
            DB::table('event_roster_members as member')
                ->join('event_rosters as roster', 'roster.id', '=', 'member.roster_id')
                ->whereIn('roster.occurrence_id', $ids)
                ->select('roster.occurrence_id', 'member.status', DB::raw('COUNT(*) AS aggregate'))
                ->groupBy('roster.occurrence_id', 'member.status')
                ->get(),
        );

        $rallies = $this->statusCounts(
            DB::table('rally_assignments as assignment')
                ->join('rally_groups as rally', 'rally.id', '=', 'assignment.rally_group_id')
                ->whereIn('rally.occurrence_id', $ids)
                ->select('rally.occurrence_id', 'assignment.status', DB::raw('COUNT(*) AS aggregate'))
                ->groupBy('rally.occurrence_id', 'assignment.status')
                ->get(),
        );

        $objectives = $this->statusCounts(
            DB::table('event_objectives')
                ->whereIn('occurrence_id', $ids)
                ->select('occurrence_id', 'status', DB::raw('COUNT(*) AS aggregate'))
                ->groupBy('occurrence_id', 'status')
                ->get(),
        );

        $objectiveAssignments = $this->totals(
            DB::table('event_objective_assignments')
                ->whereIn('occurrence_id', $ids)
                ->select('occurrence_id', DB::raw('COUNT(*) AS aggregate'))
                ->groupBy('occurrence_id')
                ->get(),
        );

        $result = [];
        foreach ($ids as $occurrenceId) {
            $attendanceStatuses = $attendance[$occurrenceId] ?? [];
            $rosterStatuses = $roster[$occurrenceId] ?? [];
            $rallyStatuses = $rallies[$occurrenceId] ?? [];
            $objectiveStatuses = $objectives[$occurrenceId] ?? [];

            $result[$occurrenceId] = [
                'attendance' => [
                    'total' => array_sum($attendanceStatuses),
                    'byStatus' => $attendanceStatuses,
                ],
                'roster' => [
                    'total' => array_sum($rosterStatuses),
                    'byStatus' => $rosterStatuses,
                ],
                'rallies' => [
                    'total' => array_sum($rallyStatuses),
                    'byStatus' => $rallyStatuses,
                ],
                'objectives' => [
                    'total' => array_sum($objectiveStatuses),
                    'assignments' => $objectiveAssignments[$occurrenceId] ?? 0,
                    'byStatus' => $objectiveStatuses,
                ],
            ];
        }

        return $result;
    }

    /**
     * @param  iterable<mixed>  $rows
     * @return array<string,array<string,int>>
     */
    private function statusCounts(iterable $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $data = (array) $row;
            $occurrenceId = (string) ($data['occurrence_id'] ?? '');
            $status = (string) ($data['status'] ?? '');
            if ($occurrenceId === '' || $status === '') {
                continue;
            }

            $result[$occurrenceId][$status] = (int) ($data['aggregate'] ?? 0);
        }

        return $result;
    }

    /**
     * @param  iterable<mixed>  $rows
     * @return array<string,int>
     */
    private function totals(iterable $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $data = (array) $row;
            $occurrenceId = (string) ($data['occurrence_id'] ?? '');
            if ($occurrenceId === '') {
                continue;
            }

            $result[$occurrenceId] = (int) ($data['aggregate'] ?? 0);
        }

        return $result;
    }
}
