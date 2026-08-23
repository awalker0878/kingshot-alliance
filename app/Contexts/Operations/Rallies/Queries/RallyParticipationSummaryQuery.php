<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Queries;

use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;

final class RallyParticipationSummaryQuery
{
    /**
     * A run is `available` only when at least one assignment has an explicit
     * participation decision (`recorded_at`). Assigned/confirmed planning state
     * does not become debrief truth.
     *
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
    public function forOccurrence(string $occurrenceId): array
    {
        $assignments = RallyAssignment::query()
            ->whereHas('rallyGroup', static fn ($query) => $query->where('occurrence_id', $occurrenceId))
            ->whereNotNull('recorded_at')
            ->orderBy('player_id')
            ->get();

        $players = [];
        $participated = 0;
        $led = 0;
        $joined = 0;
        $standby = 0;

        foreach ($assignments as $assignment) {
            $playerId = (string) $assignment->player_id;
            $players[$playerId] ??= [
                'available' => true,
                'participated' => 0,
                'led' => 0,
                'joined' => 0,
                'standby' => 0,
            ];

            if ($assignment->statusEnum() !== RallyAssignmentStatus::Participated) {
                continue;
            }

            $participated++;
            $players[$playerId]['participated']++;

            $role = $assignment->roleEnum();
            if ($role === RallyAssignmentRole::Lead) {
                $led++;
                $players[$playerId]['led']++;
            } elseif ($role === RallyAssignmentRole::Joiner) {
                $joined++;
                $players[$playerId]['joined']++;
            } else {
                $standby++;
                $players[$playerId]['standby']++;
            }
        }

        return [
            'available' => $assignments->isNotEmpty(),
            'recordedAssignments' => $assignments->count(),
            'participated' => $participated,
            'led' => $led,
            'joined' => $joined,
            'standby' => $standby,
            'players' => $players,
        ];
    }
}
