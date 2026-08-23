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
     * @param  list<string>  $occurrenceIds
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

        /** @var array<string,array{totalDamage:?int,governorCount:int,personalDamage:?int,personalRank:?int}> $scores */
        $scores = [];
        /** @var array<string,array{available:bool,total:int,present:int,absent:int,excused:int,unknown:int,ratePercent:?float,personalStatus:?string}> $attendance */
        $attendance = [];
        /** @var array<string,array{available:bool,participated:int,led:int,joined:int,personalParticipated:?int,personalLed:?int,personalJoined:?int}> $rallies */
        $rallies = [];

        foreach ($ids as $occurrenceId) {
            $scores[$occurrenceId] = [
                'totalDamage' => null,
                'governorCount' => 0,
                'personalDamage' => null,
                'personalRank' => null,
            ];
            $attendance[$occurrenceId] = [
                'available' => false,
                'total' => 0,
                'present' => 0,
                'absent' => 0,
                'excused' => 0,
                'unknown' => 0,
                'ratePercent' => null,
                'personalStatus' => null,
            ];
            $rallies[$occurrenceId] = [
                'available' => false,
                'participated' => 0,
                'led' => 0,
                'joined' => 0,
                'personalParticipated' => null,
                'personalLed' => null,
                'personalJoined' => null,
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
            if (! isset($scores[$occurrenceId])) {
                continue;
            }

            $summary = $scores[$occurrenceId];
            $summary['totalDamage'] = (int) $row->total_damage;
            $summary['governorCount'] = (int) $row->governor_count;
            $scores[$occurrenceId] = $summary;
        }

        foreach (DB::table('event_player_results')
            ->whereIn('occurrence_id', $ids)
            ->where('player_id', $actorPlayerId)
            ->get(['occurrence_id', 'score', 'rank']) as $row) {
            $occurrenceId = (string) $row->occurrence_id;
            if (! isset($scores[$occurrenceId])) {
                continue;
            }

            $summary = $scores[$occurrenceId];
            $summary['personalDamage'] = $row->score === null ? null : (int) $row->score;
            $summary['personalRank'] = $row->rank === null ? null : (int) $row->rank;
            $scores[$occurrenceId] = $summary;
        }

        foreach (DB::table('event_attendance')
            ->whereIn('occurrence_id', $ids)
            ->get(['occurrence_id', 'player_id', 'status']) as $row) {
            $occurrenceId = (string) $row->occurrence_id;
            if (! isset($attendance[$occurrenceId])) {
                continue;
            }
            $status = EventAttendanceStatus::tryFrom((string) $row->status);
            if (! $status instanceof EventAttendanceStatus) {
                continue;
            }

            $summary = $attendance[$occurrenceId];
            $summary['available'] = true;
            $summary['total']++;
            match ($status) {
                EventAttendanceStatus::Present => $summary['present']++,
                EventAttendanceStatus::Absent => $summary['absent']++,
                EventAttendanceStatus::Excused => $summary['excused']++,
                EventAttendanceStatus::Unknown => $summary['unknown']++,
            };
            if ((string) $row->player_id === $actorPlayerId) {
                $summary['personalStatus'] = $status->value;
            }
            $attendance[$occurrenceId] = $summary;
        }

        foreach ($ids as $occurrenceId) {
            $summary = $attendance[$occurrenceId];
            $decided = $summary['present'] + $summary['absent'];
            $summary['ratePercent'] = $decided === 0
                ? null
                : round(($summary['present'] / $decided) * 100, 2);
            $attendance[$occurrenceId] = $summary;
        }

        foreach (DB::table('rally_assignments as assignment')
            ->join('rally_groups as rally', 'rally.id', '=', 'assignment.rally_group_id')
            ->whereIn('rally.occurrence_id', $ids)
            ->whereNotNull('assignment.recorded_at')
            ->get(['rally.occurrence_id', 'assignment.player_id', 'assignment.role', 'assignment.status']) as $row) {
            $occurrenceId = (string) $row->occurrence_id;
            if (! isset($rallies[$occurrenceId])) {
                continue;
            }

            $summary = $rallies[$occurrenceId];
            $summary['available'] = true;
            $isActor = (string) $row->player_id === $actorPlayerId;
            if ($isActor && $summary['personalParticipated'] === null) {
                $summary['personalParticipated'] = 0;
                $summary['personalLed'] = 0;
                $summary['personalJoined'] = 0;
            }

            if ((string) $row->status === RallyAssignmentStatus::Participated->value) {
                $summary['participated']++;
                if ($isActor) {
                    $summary['personalParticipated'] = ((int) $summary['personalParticipated']) + 1;
                }

                if ((string) $row->role === RallyAssignmentRole::Lead->value) {
                    $summary['led']++;
                    if ($isActor) {
                        $summary['personalLed'] = ((int) $summary['personalLed']) + 1;
                    }
                } elseif ((string) $row->role === RallyAssignmentRole::Joiner->value) {
                    $summary['joined']++;
                    if ($isActor) {
                        $summary['personalJoined'] = ((int) $summary['personalJoined']) + 1;
                    }
                }
            }

            $rallies[$occurrenceId] = $summary;
        }

        /** @var array<string,array{totalDamage:?int,governorCount:int,personalDamage:?int,personalRank:?int,attendance:array{available:bool,total:int,present:int,absent:int,excused:int,unknown:int,ratePercent:?float,personalStatus:?string},rallies:array{available:bool,participated:int,led:int,joined:int,personalParticipated:?int,personalLed:?int,personalJoined:?int}}> $result */
        $result = [];
        foreach ($ids as $occurrenceId) {
            $score = $scores[$occurrenceId];
            $result[$occurrenceId] = [
                'totalDamage' => $score['totalDamage'],
                'governorCount' => $score['governorCount'],
                'personalDamage' => $score['personalDamage'],
                'personalRank' => $score['personalRank'],
                'attendance' => $attendance[$occurrenceId],
                'rallies' => $rallies[$occurrenceId],
            ];
        }

        return $result;
    }
}
