<?php

declare(strict_types=1);

namespace App\Domain\Events\Queries;

use App\Domain\Events\Enums\EventAttendanceStatus;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Enums\EventRosterMemberStatus;
use App\Domain\Events\Models\EventAttendance;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Models\EventRosterMember;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use App\Domain\Rallies\Models\RallyAssignment;
use Illuminate\Support\Collection;

final class EventPlayerOccurrenceEvidenceQuery
{
    /**
     * @param  Collection<int,string>  $occurrenceIds
     * @return array<string,array{committed:bool,completed:bool,absent:bool,excused:bool,unresolved:bool,outcome:string|null}>
     */
    public function forPlayer(Player $player, Collection $occurrenceIds): array
    {
        $ids = $occurrenceIds->map(static fn ($id): string => (string) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $registered = EventRegistration::query()
            ->where('player_id', $player->id)
            ->whereIn('occurrence_id', $ids)
            ->where('status', EventRegistrationStatus::Registered->value)
            ->pluck('occurrence_id')
            ->map(static fn ($id): string => (string) $id);

        $attendance = EventAttendance::query()
            ->where('player_id', $player->id)
            ->whereIn('occurrence_id', $ids)
            ->get(['occurrence_id', 'status']);

        $rosters = EventRosterMember::query()
            ->join('event_rosters', 'event_rosters.id', '=', 'event_roster_members.roster_id')
            ->where('event_roster_members.player_id', $player->id)
            ->whereIn('event_rosters.occurrence_id', $ids)
            ->whereIn('event_roster_members.status', [
                EventRosterMemberStatus::Confirmed->value,
                EventRosterMemberStatus::Participated->value,
                EventRosterMemberStatus::Absent->value,
            ])
            ->get(['event_rosters.occurrence_id', 'event_roster_members.status']);

        $rallies = RallyAssignment::query()
            ->join('rally_groups', 'rally_groups.id', '=', 'rally_assignments.rally_group_id')
            ->where('rally_assignments.player_id', $player->id)
            ->whereIn('rally_groups.occurrence_id', $ids)
            ->whereIn('rally_assignments.status', [
                RallyAssignmentStatus::Confirmed->value,
                RallyAssignmentStatus::Participated->value,
                RallyAssignmentStatus::Absent->value,
            ])
            ->get(['rally_groups.occurrence_id', 'rally_assignments.status']);

        $result = [];
        foreach ($ids as $occurrenceId) {
            $registrationCommitted = $registered->contains($occurrenceId);
            $attendanceRows = $attendance->where('occurrence_id', $occurrenceId);
            $rosterRows = $rosters->where('occurrence_id', $occurrenceId);
            $rallyRows = $rallies->where('occurrence_id', $occurrenceId);

            $completed = $attendanceRows->contains('status', EventAttendanceStatus::Present->value)
                || $rosterRows->contains('status', EventRosterMemberStatus::Participated->value)
                || $rallyRows->contains('status', RallyAssignmentStatus::Participated->value);
            $excused = ! $completed
                && $attendanceRows->contains('status', EventAttendanceStatus::Excused->value);
            $absent = ! $completed
                && ! $excused
                && ($attendanceRows->contains('status', EventAttendanceStatus::Absent->value)
                    || $rosterRows->contains('status', EventRosterMemberStatus::Absent->value)
                    || $rallyRows->contains('status', RallyAssignmentStatus::Absent->value));
            $committed = $registrationCommitted || $rosterRows->isNotEmpty() || $rallyRows->isNotEmpty();
            $unresolved = $committed && ! $completed && ! $excused && ! $absent;

            $result[$occurrenceId] = [
                'committed' => $committed,
                'completed' => $completed,
                'absent' => $absent,
                'excused' => $excused,
                'unresolved' => $unresolved,
                'outcome' => $completed ? 'completed' : ($excused ? 'excused' : ($absent ? 'absent' : ($unresolved ? 'unresolved' : null))),
            ];
        }

        return $result;
    }
}
