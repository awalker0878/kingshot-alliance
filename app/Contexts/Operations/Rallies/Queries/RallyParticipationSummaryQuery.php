<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Queries;

use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RallyParticipationSummaryQuery
{
    /**
     * A run is `available` only when at least one assignment has an explicit
     * participation decision (`recorded_at`). Assigned/confirmed planning state
     * does not become debrief truth.
     *
     * @param  list<string>  $playerIds
     * @return array{
     *   available:bool,
     *   recordedAssignments:int,
     *   participated:int,
     *   led:int,
     *   joined:int,
     *   standby:int,
     *   players:array<string,array{available:bool,participated:int,led:int,joined:int,standby:int}>
     * }
     */
    public function forOccurrence(string $occurrenceId, array $playerIds = []): array
    {
        if (count($playerIds) > 26) {
            throw ValidationException::withMessages(['players' => 'Rally details support at most one Debrief page and its current Governor.']);
        }
        $query = DB::table('rally_assignments as assignment')
            ->join('rally_groups as rally', 'rally.id', '=', 'assignment.rally_group_id')
            ->where('rally.occurrence_id', $occurrenceId)->whereNotNull('assignment.recorded_at');
        $select = 'COUNT(*) AS recorded, SUM(CASE WHEN assignment.status = ? THEN 1 ELSE 0 END) AS participated, '
            .'SUM(CASE WHEN assignment.status = ? AND assignment.role = ? THEN 1 ELSE 0 END) AS led, '
            .'SUM(CASE WHEN assignment.status = ? AND assignment.role = ? THEN 1 ELSE 0 END) AS joined, '
            .'SUM(CASE WHEN assignment.status = ? AND assignment.role NOT IN (?, ?) THEN 1 ELSE 0 END) AS standby';
        $bindings = [RallyAssignmentStatus::Participated->value, RallyAssignmentStatus::Participated->value, RallyAssignmentRole::Lead->value,
            RallyAssignmentStatus::Participated->value, RallyAssignmentRole::Joiner->value,
            RallyAssignmentStatus::Participated->value, RallyAssignmentRole::Lead->value, RallyAssignmentRole::Joiner->value];
        $summary = (clone $query)->selectRaw($select, $bindings)->first();
        $players = [];
        foreach ($query->whereIn('assignment.player_id', $playerIds)->select('assignment.player_id')
            ->selectRaw($select, $bindings)->groupBy('assignment.player_id')->get() as $row) {
            $players[(string) $row->player_id] = ['available' => true, 'participated' => (int) $row->participated,
                'led' => (int) $row->led, 'joined' => (int) $row->joined, 'standby' => (int) $row->standby];
        }

        return ['available' => (int) ($summary->recorded ?? 0) > 0, 'recordedAssignments' => (int) ($summary->recorded ?? 0),
            'participated' => (int) ($summary->participated ?? 0), 'led' => (int) ($summary->led ?? 0),
            'joined' => (int) ($summary->joined ?? 0), 'standby' => (int) ($summary->standby ?? 0), 'players' => $players];
    }
}
