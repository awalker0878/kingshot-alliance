<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\EventAnalysis\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

final class EventPlayerOccurrenceEvidenceQuery
{
    /**
     * Apply the same participation-outcome semantics used by forPlayer() directly
     * to a history query so pagination/limits happen after evidence filtering.
     */
    public function applyOutcomeFilter(
        Builder $query,
        PlayerReference $player,
        string $outcome,
        string $occurrenceColumn = 'context.occurrence_id',
    ): void {
        match ($outcome) {
            'completed' => $this->whereCompleted($query, $player, $occurrenceColumn),
            'excused' => $this->whereExcused($query, $player, $occurrenceColumn),
            'absent' => $this->whereAbsent($query, $player, $occurrenceColumn),
            'unresolved' => $this->whereUnresolved($query, $player, $occurrenceColumn),
            default => null,
        };
    }

    /**
     * @param  Collection<int,string>  $occurrenceIds
     * @return array<string,array{committed:bool,completed:bool,absent:bool,excused:bool,unresolved:bool,outcome:string|null}>
     */
    public function forPlayer(PlayerReference $player, Collection $occurrenceIds): array
    {
        $ids = $occurrenceIds->map(static fn ($id): string => (string) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $registered = EventRegistration::query()
            ->where('player_id', $player->playerId)
            ->whereIn('occurrence_id', $ids)
            ->where('status', EventRegistrationStatus::Registered->value)
            ->pluck('occurrence_id')
            ->map(static fn ($id): string => (string) $id);

        $playerResults = EventPlayerResult::query()
            ->where('player_id', $player->playerId)
            ->whereIn('occurrence_id', $ids)
            ->get(['occurrence_id', 'outcome']);

        $attendance = EventAttendance::query()
            ->where('player_id', $player->playerId)
            ->whereIn('occurrence_id', $ids)
            ->get(['occurrence_id', 'status']);

        $rosters = EventRosterMember::query()
            ->join('event_rosters', 'event_rosters.id', '=', 'event_roster_members.roster_id')
            ->where('event_roster_members.player_id', $player->playerId)
            ->whereIn('event_rosters.occurrence_id', $ids)
            ->whereIn('event_roster_members.status', [
                EventRosterMemberStatus::Confirmed->value,
                EventRosterMemberStatus::Participated->value,
                EventRosterMemberStatus::Absent->value,
            ])
            ->get(['event_rosters.occurrence_id', 'event_roster_members.status']);

        $rallies = RallyAssignment::query()
            ->join('rally_groups', 'rally_groups.id', '=', 'rally_assignments.rally_group_id')
            ->where('rally_assignments.player_id', $player->playerId)
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
            $resultOutcomes = $playerResults
                ->where('occurrence_id', $occurrenceId)
                ->pluck('outcome')
                ->filter(static fn ($value): bool => $value !== null && trim((string) $value) !== '')
                ->map(static fn ($value): string => mb_strtolower(trim((string) $value)));
            $attendanceRows = $attendance->where('occurrence_id', $occurrenceId);
            $rosterRows = $rosters->where('occurrence_id', $occurrenceId);
            $rallyRows = $rallies->where('occurrence_id', $occurrenceId);

            $completed = $resultOutcomes->contains('completed')
                || $attendanceRows->contains('status', EventAttendanceStatus::Present->value)
                || $rosterRows->contains('status', EventRosterMemberStatus::Participated->value)
                || $rallyRows->contains('status', RallyAssignmentStatus::Participated->value);
            $excused = ! $completed
                && ($resultOutcomes->contains('excused')
                    || $attendanceRows->contains('status', EventAttendanceStatus::Excused->value));
            $absent = ! $completed
                && ! $excused
                && ($resultOutcomes->contains('absent')
                    || $attendanceRows->contains('status', EventAttendanceStatus::Absent->value)
                    || $rosterRows->contains('status', EventRosterMemberStatus::Absent->value)
                    || $rallyRows->contains('status', RallyAssignmentStatus::Absent->value));
            $resultCommitted = $resultOutcomes->contains(
                static fn (string $outcome): bool => in_array($outcome, ['completed', 'excused', 'absent', 'unresolved'], true),
            );
            $committed = $resultCommitted || $registrationCommitted || $rosterRows->isNotEmpty() || $rallyRows->isNotEmpty();
            $unresolved = ! $completed
                && ! $excused
                && ! $absent
                && ($resultOutcomes->contains('unresolved') || $committed);

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

    private function whereCompleted(Builder $query, PlayerReference $player, string $occurrenceColumn): void
    {
        $query->where(function (Builder $evidence) use ($player, $occurrenceColumn): void {
            $this->orWhereResultOutcome($evidence, $player, $occurrenceColumn, 'completed')
                ->orWhere(function (Builder $nested) use ($player, $occurrenceColumn): void {
                    $this->whereAttendance($nested, $player, $occurrenceColumn, EventAttendanceStatus::Present->value);
                })
                ->orWhere(function (Builder $nested) use ($player, $occurrenceColumn): void {
                    $this->whereRoster($nested, $player, $occurrenceColumn, [EventRosterMemberStatus::Participated->value]);
                })
                ->orWhere(function (Builder $nested) use ($player, $occurrenceColumn): void {
                    $this->whereRally($nested, $player, $occurrenceColumn, [RallyAssignmentStatus::Participated->value]);
                });
        });
    }

    private function whereExcused(Builder $query, PlayerReference $player, string $occurrenceColumn): void
    {
        $this->whereNotCompleted($query, $player, $occurrenceColumn);
        $query->where(function (Builder $evidence) use ($player, $occurrenceColumn): void {
            $this->orWhereResultOutcome($evidence, $player, $occurrenceColumn, 'excused')
                ->orWhere(function (Builder $nested) use ($player, $occurrenceColumn): void {
                    $this->whereAttendance($nested, $player, $occurrenceColumn, EventAttendanceStatus::Excused->value);
                });
        });
    }

    private function whereAbsent(Builder $query, PlayerReference $player, string $occurrenceColumn): void
    {
        $this->whereNotCompleted($query, $player, $occurrenceColumn);
        $this->whereNotResultOutcome($query, $player, $occurrenceColumn, 'excused');
        $this->whereNotAttendance($query, $player, $occurrenceColumn, EventAttendanceStatus::Excused->value);
        $query->where(function (Builder $evidence) use ($player, $occurrenceColumn): void {
            $this->orWhereResultOutcome($evidence, $player, $occurrenceColumn, 'absent')
                ->orWhere(function (Builder $nested) use ($player, $occurrenceColumn): void {
                    $this->whereAttendance($nested, $player, $occurrenceColumn, EventAttendanceStatus::Absent->value);
                })
                ->orWhere(function (Builder $nested) use ($player, $occurrenceColumn): void {
                    $this->whereRoster($nested, $player, $occurrenceColumn, [EventRosterMemberStatus::Absent->value]);
                })
                ->orWhere(function (Builder $nested) use ($player, $occurrenceColumn): void {
                    $this->whereRally($nested, $player, $occurrenceColumn, [RallyAssignmentStatus::Absent->value]);
                });
        });
    }

    private function whereUnresolved(Builder $query, PlayerReference $player, string $occurrenceColumn): void
    {
        $query->where(function (Builder $evidence) use ($player, $occurrenceColumn): void {
            $this->orWhereRegistration($evidence, $player, $occurrenceColumn);
            $this->orWhereResultOutcome($evidence, $player, $occurrenceColumn, 'unresolved');
            $evidence->orWhere(function (Builder $nested) use ($player, $occurrenceColumn): void {
                $this->whereRoster($nested, $player, $occurrenceColumn, [
                    EventRosterMemberStatus::Confirmed->value,
                    EventRosterMemberStatus::Participated->value,
                    EventRosterMemberStatus::Absent->value,
                ]);
            })
                ->orWhere(function (Builder $nested) use ($player, $occurrenceColumn): void {
                    $this->whereRally($nested, $player, $occurrenceColumn, [
                        RallyAssignmentStatus::Confirmed->value,
                        RallyAssignmentStatus::Participated->value,
                        RallyAssignmentStatus::Absent->value,
                    ]);
                });
        });
        $this->whereNotCompleted($query, $player, $occurrenceColumn);
        $this->whereNotResultOutcome($query, $player, $occurrenceColumn, 'excused');
        $this->whereNotResultOutcome($query, $player, $occurrenceColumn, 'absent');
        $this->whereNotAttendance($query, $player, $occurrenceColumn, EventAttendanceStatus::Excused->value);
        $this->whereNotAttendance($query, $player, $occurrenceColumn, EventAttendanceStatus::Absent->value);
        $this->whereNotRoster($query, $player, $occurrenceColumn, [EventRosterMemberStatus::Absent->value]);
        $this->whereNotRally($query, $player, $occurrenceColumn, [RallyAssignmentStatus::Absent->value]);
    }

    private function whereNotCompleted(Builder $query, PlayerReference $player, string $occurrenceColumn): void
    {
        $this->whereNotResultOutcome($query, $player, $occurrenceColumn, 'completed');
        $this->whereNotAttendance($query, $player, $occurrenceColumn, EventAttendanceStatus::Present->value);
        $this->whereNotRoster($query, $player, $occurrenceColumn, [EventRosterMemberStatus::Participated->value]);
        $this->whereNotRally($query, $player, $occurrenceColumn, [RallyAssignmentStatus::Participated->value]);
    }

    private function orWhereResultOutcome(Builder $query, PlayerReference $player, string $occurrenceColumn, string $outcome): Builder
    {
        return $query->orWhereExists(static function (Builder $subquery) use ($player, $occurrenceColumn, $outcome): void {
            $subquery->selectRaw('1')
                ->from('event_player_results as participation_player_result')
                ->whereColumn('participation_player_result.occurrence_id', $occurrenceColumn)
                ->where('participation_player_result.player_id', $player->playerId)
                ->whereRaw("LOWER(TRIM(COALESCE(participation_player_result.outcome, ''))) = ?", [$outcome]);
        });
    }

    private function whereNotResultOutcome(Builder $query, PlayerReference $player, string $occurrenceColumn, string $outcome): Builder
    {
        return $query->whereNotExists(static function (Builder $subquery) use ($player, $occurrenceColumn, $outcome): void {
            $subquery->selectRaw('1')
                ->from('event_player_results as participation_player_result')
                ->whereColumn('participation_player_result.occurrence_id', $occurrenceColumn)
                ->where('participation_player_result.player_id', $player->playerId)
                ->whereRaw("LOWER(TRIM(COALESCE(participation_player_result.outcome, ''))) = ?", [$outcome]);
        });
    }

    private function whereAttendance(Builder $query, PlayerReference $player, string $occurrenceColumn, string $status): Builder
    {
        return $query->whereExists(static function (Builder $subquery) use ($player, $occurrenceColumn, $status): void {
            $subquery->selectRaw('1')
                ->from('event_attendance as participation_attendance')
                ->whereColumn('participation_attendance.occurrence_id', $occurrenceColumn)
                ->where('participation_attendance.player_id', $player->playerId)
                ->where('participation_attendance.status', $status);
        });
    }

    private function whereNotAttendance(Builder $query, PlayerReference $player, string $occurrenceColumn, string $status): Builder
    {
        return $query->whereNotExists(static function (Builder $subquery) use ($player, $occurrenceColumn, $status): void {
            $subquery->selectRaw('1')
                ->from('event_attendance as participation_attendance')
                ->whereColumn('participation_attendance.occurrence_id', $occurrenceColumn)
                ->where('participation_attendance.player_id', $player->playerId)
                ->where('participation_attendance.status', $status);
        });
    }

    private function orWhereRegistration(Builder $query, PlayerReference $player, string $occurrenceColumn): Builder
    {
        return $query->orWhereExists(static function (Builder $subquery) use ($player, $occurrenceColumn): void {
            $subquery->selectRaw('1')
                ->from('event_registrations as participation_registration')
                ->whereColumn('participation_registration.occurrence_id', $occurrenceColumn)
                ->where('participation_registration.player_id', $player->playerId)
                ->where('participation_registration.status', EventRegistrationStatus::Registered->value);
        });
    }

    /** @param list<string> $statuses */
    private function whereRoster(Builder $query, PlayerReference $player, string $occurrenceColumn, array $statuses): Builder
    {
        return $query->whereExists(static function (Builder $subquery) use ($player, $occurrenceColumn, $statuses): void {
            $subquery->selectRaw('1')
                ->from('event_roster_members as participation_roster_member')
                ->join('event_rosters as participation_roster', 'participation_roster.id', '=', 'participation_roster_member.roster_id')
                ->whereColumn('participation_roster.occurrence_id', $occurrenceColumn)
                ->where('participation_roster_member.player_id', $player->playerId)
                ->whereIn('participation_roster_member.status', $statuses);
        });
    }

    /** @param list<string> $statuses */
    private function whereNotRoster(Builder $query, PlayerReference $player, string $occurrenceColumn, array $statuses): Builder
    {
        return $query->whereNotExists(static function (Builder $subquery) use ($player, $occurrenceColumn, $statuses): void {
            $subquery->selectRaw('1')
                ->from('event_roster_members as participation_roster_member')
                ->join('event_rosters as participation_roster', 'participation_roster.id', '=', 'participation_roster_member.roster_id')
                ->whereColumn('participation_roster.occurrence_id', $occurrenceColumn)
                ->where('participation_roster_member.player_id', $player->playerId)
                ->whereIn('participation_roster_member.status', $statuses);
        });
    }

    /** @param list<string> $statuses */
    private function whereRally(Builder $query, PlayerReference $player, string $occurrenceColumn, array $statuses): Builder
    {
        return $query->whereExists(static function (Builder $subquery) use ($player, $occurrenceColumn, $statuses): void {
            $subquery->selectRaw('1')
                ->from('rally_assignments as participation_rally_assignment')
                ->join('rally_groups as participation_rally_group', 'participation_rally_group.id', '=', 'participation_rally_assignment.rally_group_id')
                ->whereColumn('participation_rally_group.occurrence_id', $occurrenceColumn)
                ->where('participation_rally_assignment.player_id', $player->playerId)
                ->whereIn('participation_rally_assignment.status', $statuses);
        });
    }

    /** @param list<string> $statuses */
    private function whereNotRally(Builder $query, PlayerReference $player, string $occurrenceColumn, array $statuses): Builder
    {
        return $query->whereNotExists(static function (Builder $subquery) use ($player, $occurrenceColumn, $statuses): void {
            $subquery->selectRaw('1')
                ->from('rally_assignments as participation_rally_assignment')
                ->join('rally_groups as participation_rally_group', 'participation_rally_group.id', '=', 'participation_rally_assignment.rally_group_id')
                ->whereColumn('participation_rally_group.occurrence_id', $occurrenceColumn)
                ->where('participation_rally_assignment.player_id', $player->playerId)
                ->whereIn('participation_rally_assignment.status', $statuses);
        });
    }
}
