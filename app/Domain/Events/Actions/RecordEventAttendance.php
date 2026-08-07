<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Events\Services\EventOutbox;

use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordEventAttendance
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private EventOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        EventOccurrence $occurrence,
        EventRegistration $registration,
        EventRegistrationStatus $attendanceStatus,
    ): EventRegistration {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::EventManage)) {
            throw new AuthorizationException('You are not allowed to record event attendance.');
        }

        if ($occurrence->alliance_id !== $alliance->id
            || $registration->alliance_id !== $alliance->id
            || $registration->occurrence_id !== $occurrence->id) {
            throw new AuthorizationException('The attendance record does not belong to the active alliance occurrence.');
        }

        if (! in_array($attendanceStatus, [EventRegistrationStatus::Attended, EventRegistrationStatus::NoShow], true)) {
            throw new InvalidArgumentException('Attendance status must be attended or no-show.');
        }

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $occurrence,
            $registration,
            $attendanceStatus,
        ): EventRegistration {
            $locked = EventRegistration::query()
                ->whereKey($registration->id)
                ->where('alliance_id', $alliance->id)
                ->where('occurrence_id', $occurrence->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === EventRegistrationStatus::Cancelled) {
                throw new InvalidArgumentException('Cancelled registrations cannot receive attendance.');
            }

            $locked->forceFill([
                'status' => $attendanceStatus,
                'waitlist_position' => null,
                'attendance_recorded_at' => now(),
                'attendance_recorded_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record('event.attendance.recorded', $actor, $locked, $alliance, [
                'occurrence_id' => $occurrence->id,
                'status' => $attendanceStatus->value,
            ]);
            $this->outbox->record('event.attendance.recorded', $alliance, $locked, [
                'occurrence_id' => $occurrence->id,
                'membership_id' => $locked->membership_id,
                'status' => $attendanceStatus->value,
            ]);

            return $locked;
        });
    }
}
