<?php

declare(strict_types=1);

namespace App\ReadModels\EventAnalysis\Queries;

use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Results\Services\ResultScoreTotal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
     *   totalDamage:int|string|null,
     *   governorCount:int,
     *   personalDamage:int|string|null,
     *   personalRank:?int,
     *   personalAccepted:bool,
     *   attendance:array{available:bool,total:int,present:int,absent:int,excused:int,unknown:int,ratePercent:?float,personalStatus:?string},
     *   rallies:array{available:bool,participated:int,led:int,joined:int,personalParticipated:?int,personalLed:?int,personalJoined:?int}
     * }>
     */
    public function forOccurrences(array $occurrenceIds, string $actorPlayerId): array
    {
        if (count($occurrenceIds) > self::MAX_RUNS) {
            throw ValidationException::withMessages(['history' => 'Debrief history supports at most 24 requested runs.']);
        }
        $ids = array_values(array_unique(array_filter(
            array_map('trim', $occurrenceIds),
            static fn (string $id): bool => $id !== '',
        )));
        if ($ids === []) {
            return [];
        }

        /** @var array<string,array{totalDamage:int|string|null,governorCount:int,personalDamage:int|string|null,personalRank:?int,personalAccepted:bool}> $scores */
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
                'personalAccepted' => false,
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
            $summary['totalDamage'] = ResultScoreTotal::fromDatabase($row->total_damage);
            $summary['governorCount'] = (int) $row->governor_count;
            $scores[$occurrenceId] = $summary;
        }

        foreach (DB::table('bear_hunt_battle_report_entries as entry')
            ->join('bear_hunt_battle_reports as report', 'report.id', '=', 'entry.report_id')
            ->whereIn('report.occurrence_id', $ids)
            ->where('report.status', 'accepted')
            ->where('entry.player_id', $actorPlayerId)
            ->distinct()
            ->pluck('report.occurrence_id') as $occurrenceId) {
            $id = (string) $occurrenceId;
            if (! isset($scores[$id])) {
                continue;
            }

            $summary = $scores[$id];
            $summary['personalAccepted'] = true;
            $scores[$id] = $summary;
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
            $summary['personalDamage'] = ResultScoreTotal::fromDatabase($row->score);
            $summary['personalRank'] = $row->rank === null ? null : (int) $row->rank;
            $scores[$occurrenceId] = $summary;
        }

        foreach (DB::table('event_attendance')->whereIn('occurrence_id', $ids)
            ->selectRaw('occurrence_id, status, COUNT(*) AS aggregate, MAX(CASE WHEN player_id = ? THEN 1 ELSE 0 END) AS personal', [$actorPlayerId])
            ->groupBy('occurrence_id', 'status')->get() as $row) {
            $occurrenceId = (string) $row->occurrence_id;
            $status = EventAttendanceStatus::tryFrom((string) $row->status);
            if (! isset($attendance[$occurrenceId]) || ! $status instanceof EventAttendanceStatus) {
                continue;
            }
            $summary = $attendance[$occurrenceId];
            $summary['available'] = true;
            $summary['total'] += (int) $row->aggregate;
            $summary[$status->value] = (int) $row->aggregate;
            if ((int) $row->personal === 1) {
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
            ->whereIn('rally.occurrence_id', $ids)->whereNotNull('assignment.recorded_at')
            ->selectRaw('rally.occurrence_id, assignment.role, assignment.status, COUNT(*) AS aggregate, SUM(CASE WHEN assignment.player_id = ? THEN 1 ELSE 0 END) AS personal', [$actorPlayerId])
            ->groupBy('rally.occurrence_id', 'assignment.role', 'assignment.status')->get() as $row) {
            $occurrenceId = (string) $row->occurrence_id;
            if (! isset($rallies[$occurrenceId])) {
                continue;
            }
            $summary = $rallies[$occurrenceId];
            $summary['available'] = true;
            $personal = (int) $row->personal;
            if ($personal > 0 && $summary['personalParticipated'] === null) {
                $summary['personalParticipated'] = 0;
                $summary['personalLed'] = 0;
                $summary['personalJoined'] = 0;
            }
            if ((string) $row->status === RallyAssignmentStatus::Participated->value) {
                $summary['participated'] += (int) $row->aggregate;
                if ($personal > 0) {
                    $summary['personalParticipated'] = (int) $summary['personalParticipated'] + $personal;
                }
                if ((string) $row->role === RallyAssignmentRole::Lead->value) {
                    $summary['led'] += (int) $row->aggregate;
                    if ($personal > 0) {
                        $summary['personalLed'] = (int) $summary['personalLed'] + $personal;
                    }
                } elseif ((string) $row->role === RallyAssignmentRole::Joiner->value) {
                    $summary['joined'] += (int) $row->aggregate;
                    if ($personal > 0) {
                        $summary['personalJoined'] = (int) $summary['personalJoined'] + $personal;
                    }
                }
            }
            $rallies[$occurrenceId] = $summary;
        }

        /** @var array<string,array{totalDamage:int|string|null,governorCount:int,personalDamage:int|string|null,personalRank:?int,personalAccepted:bool,attendance:array{available:bool,total:int,present:int,absent:int,excused:int,unknown:int,ratePercent:?float,personalStatus:?string},rallies:array{available:bool,participated:int,led:int,joined:int,personalParticipated:?int,personalLed:?int,personalJoined:?int}}> $result */
        $result = [];
        foreach ($ids as $occurrenceId) {
            $score = $scores[$occurrenceId];
            $result[$occurrenceId] = [
                'totalDamage' => $score['totalDamage'],
                'governorCount' => $score['governorCount'],
                'personalDamage' => $score['personalDamage'],
                'personalRank' => $score['personalRank'],
                'personalAccepted' => $score['personalAccepted'],
                'attendance' => $attendance[$occurrenceId],
                'rallies' => $rallies[$occurrenceId],
            ];
        }

        return $result;
    }
}
