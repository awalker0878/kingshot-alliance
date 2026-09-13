<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Queries;

use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use Illuminate\Validation\ValidationException;

final class BearHuntAttendanceSummaryQuery
{
    /**
     * @param  list<string>  $playerIds
     * @return array{
     *   available:bool,
     *   total:int,
     *   ratePercent:?float,
     *   byStatus:array{present:int,absent:int,excused:int,unknown:int},
     *   players:array<string,array{status:string,recordedAt:?string}>
     * }
     */
    public function forOccurrence(string $occurrenceId, array $playerIds = []): array
    {
        if (count($playerIds) > 26) {
            throw ValidationException::withMessages(['players' => 'Attendance details support at most one Debrief page and its current Governor.']);
        }
        $query = EventAttendance::query()->where('occurrence_id', $occurrenceId);
        $counts = (clone $query)->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $rows = $query->whereIn('player_id', $playerIds)->orderBy('player_id')->limit(26)->get();

        $byStatus = [
            EventAttendanceStatus::Present->value => 0,
            EventAttendanceStatus::Absent->value => 0,
            EventAttendanceStatus::Excused->value => 0,
            EventAttendanceStatus::Unknown->value => 0,
        ];
        foreach ($byStatus as $status => $count) {
            $byStatus[$status] = (int) ($counts[$status] ?? 0);
        }
        $players = [];

        foreach ($rows as $row) {
            $status = $row->status instanceof EventAttendanceStatus
                ? $row->status->value
                : (string) $row->getRawOriginal('status');
            if (! array_key_exists($status, $byStatus)) {
                continue;
            }

            $players[(string) $row->player_id] = [
                'status' => $status,
                'recordedAt' => $row->recorded_at?->toIso8601String(),
            ];
        }

        // Excused/unknown Governors are excluded from the attendance-rate denominator.
        $decided = $byStatus[EventAttendanceStatus::Present->value]
            + $byStatus[EventAttendanceStatus::Absent->value];

        return [
            'available' => array_sum($byStatus) > 0,
            'total' => array_sum($byStatus),
            'ratePercent' => $decided === 0
                ? null
                : round(($byStatus[EventAttendanceStatus::Present->value] / $decided) * 100, 2),
            'byStatus' => $byStatus,
            'players' => $players,
        ];
    }
}
