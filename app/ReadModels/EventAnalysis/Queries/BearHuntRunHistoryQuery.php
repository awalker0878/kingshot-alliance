<?php

declare(strict_types=1);

namespace App\ReadModels\EventAnalysis\Queries;

use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use Illuminate\Support\Facades\DB;

final class BearHuntRunHistoryQuery
{
    private const MAX_RUNS = 24;

    /**
     * Batch historical facts for a bounded set of already-authorized Bear Hunt
     * occurrence IDs. This is a read-model projection only; owner contexts remain
     * authoritative for writes and validation.
     *
     * @param list<string> $occurrenceIds
     * @return array<string,array{
     *   totalDamage:?int,
     *   governorCount:int,
     *   personalDamage:?int,
     *   personalRank:?int,
     *   attendance:array{available:bool,total:int,present:int,absent:int,excused:int,unknown:int,ratePercent:?float,personalStatus:?string},
     *   rallies:array{available:bool,participated:int,led:int,joined:int,personalParticipated:?int,personalLed:?int,personalJoined:?int}
     * }>
     */
    public function forOccurrences(array $occurrenceIds, string $actorPlayerId): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('trim', $occurrenceIds),
            static fn (string $id): bool => $id !== '',
        )));
        $ids = array_slice($ids, 0, self::MAX_RUNS);
        if ($ids === []) {
            return [];
        }

        $result = [];
        foreach ($ids as $occurrenceId) {
            $result[$occurrenceId] = [
                'totalDamage' => null,
                'governorCount' => 0,
                'personalDamage' => null,
                'personalRank' => null,
                'attendance' => [
                    'available' => false,
                    'total' => 0,
                    'present' => 0,
                    'absent' => 0,
                    'excused' => 0,
                    'unknown' => 0,
                    'ratePercent' => null,
                    'personalStatus' => null,
                ],
                'rallies' => [
                    'available' => false,
                    'participated' => 0,
                    'led' => 0,
                    'joined' => 0,
                    'personalParticipated' => null,
                    'personalLed' => null,
                    'personalJoined' => null,
                ],
            ];
        }

        foreach (DB::table('event_player_results')
            ->whereIn('occurrence_id', $ids)
            ->whereNotNull('score')
            ->groupBy('occurrence_id')
            ->get([
                'occurrence_id',
                DB::raw('SUM(score) AS total_damage'),
                DB::raw('COUNT(*) AS governor_count'),
            ]) as $row) {
            $occurrenceId = (string) $row->occurrence_id;
            if (! isset($result[$occurrenceId])) {
                continue;
            }
            $result[$occurrenceId]['totalDamage'] = (int) $row->total_damage;
            $result[$occurrenceId]['governorCount'] = (int) $row->governor_count;
        }

        foreach (DB::table('event_player_results')
            ->whereIn('occurrence_id', $ids)
            ->where('player_id', $actorPlayerId)
            ->get(['occurrence_id', 'score', 'rank']) as $row) {
            $occurrenceId = (string) $row->occurrence_id;
            if (! isset($result[$occurrenceId])) {
                continue;
            }
            $result[$occurrenceId]['personalDamage'] = $row->score === null ? null : (int) $row->score;
            $result[$occurrenceId]['personalRank'] = $row->rank === null ? null : (int) $row->rank;
        }

        foreach (DB::table('event_attendance')
            ->whereIn('occurrence_id', $ids)
            ->get(['occurrence_id', 'player_id', 'status']) as $row) {
            $occurrenceId = (string) $row->occurrence_id;
            if (! isset($result[$occurrenceId])) {
                continue;
            }
            $status = (string) $row->status;
            if (! in_array($status, array_map(static fn (EventAttendanceStatus $case): string => $case->value, EventAttendanceStatus::cases()), true)) {
                continue;
            }

            $result[$occurrenceId]['attendance']['available'] = true;
            $result[$occurrenceId]['attendance']['total'] += 1;
            $result[$occurrenceId]['attendance'][$status] += 1;
            if ((string) $row->player_id === $actorPlayerId) {
                $result[$occurrenceId]['attendance']['personalStatus'] = $status;
            }
        }

        foreach ($result as &$run) {
            $decided = $run['attendance']['present'] + $run['attendance']['absent'];
            $run['attendance']['ratePercent'] = $decided === 0
                ? null
                : round(($run['attendance']['present'] / $decided) * 100, 2);
        }
        unset($run);

        foreach (DB::table('rally_assignments as assignment')
            ->join('rally_groups as rally', 'rally.id', '=', 'assignment.rally_group_id')
            ->whereIn('rally.occurrence_id', $ids)
            ->whereNotNull('assignment.recorded_at')
            ->get(['rally.occurrence_id', 'assignment.player_id', 'assignment.role', 'assignment.status']) as $row) {
            $occurrenceId = (string) $row->occurrence_id;
            if (! isset($result[$occurrenceId])) {
                continue;
            }

            $result[$occurrenceId]['rallies']['available'] = true;
            $isActor = (string) $row->player_id === $actorPlayerId;
            if ($isActor && $result[$occurrenceId]['rallies']['personalParticipated'] === null) {
                $result[$occurrenceId]['rallies']['personalParticipated'] = 0;
                $result[$occurrenceId]['rallies']['personalLed'] = 0;
                $result[$occurrenceId]['rallies']['personalJoined'] = 0;
            }

            if ((string) $row->status !== RallyAssignmentStatus::Participated->value) {
                continue;
            }

            $result[$occurrenceId]['rallies']['participated'] += 1;
            if ($isActor) {
                $result[$occurrenceId]['rallies']['personalParticipated'] += 1;
            }

            if ((string) $row->role === RallyAssignmentRole::Lead->value) {
                $result[$occurrenceId]['rallies']['led'] += 1;
                if ($isActor) {
                    $result[$occurrenceId]['rallies']['personalLed'] += 1;
                }
            } elseif ((string) $row->role === RallyAssignmentRole::Joiner->value) {
                $result[$occurrenceId]['rallies']['joined'] += 1;
                if ($isActor) {
                    $result[$occurrenceId]['rallies']['personalJoined'] += 1;
                }
            }
        }

        return $result;
    }
}
