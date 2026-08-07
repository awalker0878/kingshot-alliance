<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Events\Services\EventOutbox;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Models\AllianceMembership;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Identity\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RegisterForEvent
{
    public function __construct(
        private AuditRecorder $audit,
        private EventOutbox $outbox,
    ) {}

    public function handle(User $actor, Alliance $alliance, string $occurrenceId): EventRegistration
    {
        return DB::transaction(function () use ($actor, $alliance, $occurrenceId): EventRegistration {
            $membership = AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->where('user_id', $actor->id)
                ->where('status', MembershipStatus::Active->value)
                ->first();

            if (! $membership instanceof AllianceMembership) {
                throw new DomainException('An active alliance membership is required to register.');
            }

            $occurrence = EventOccurrence::query()
                ->where('id', $occurrenceId)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($occurrence->status !== EventOccurrenceStatus::Scheduled) {
                throw new DomainException('This event occurrence is not open for registration.');
            }

            $now = now();

            if ($occurrence->registration_opens_at !== null && $now->lt($occurrence->registration_opens_at)) {
                throw new DomainException('Registration has not opened yet.');
            }

            if ($occurrence->registration_closes_at !== null && ! $now->lt($occurrence->registration_closes_at)) {
                throw new DomainException('Registration is closed.');
            }

            $existing = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('membership_id', $membership->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof EventRegistration
                && in_array($existing->status, [EventRegistrationStatus::Registered, EventRegistrationStatus::Waitlisted], true)) {
                return $existing;
            }

            if ($existing instanceof EventRegistration
                && in_array($existing->status, [EventRegistrationStatus::Attended, EventRegistrationStatus::NoShow], true)) {
                throw new DomainException('Attendance has already been finalized for this occurrence.');
            }

            $registeredCount = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('status', EventRegistrationStatus::Registered->value)
                ->count();

            $isFull = $occurrence->capacity !== null && $registeredCount >= $occurrence->capacity;
            $status = $isFull ? EventRegistrationStatus::Waitlisted : EventRegistrationStatus::Registered;
            $waitlistPosition = null;

            if ($status === EventRegistrationStatus::Waitlisted) {
                $highestPosition = (int) EventRegistration::query()
                    ->where('occurrence_id', $occurrence->id)
                    ->where('status', EventRegistrationStatus::Waitlisted->value)
                    ->max('waitlist_position');
                $waitlistPosition = $highestPosition + 1;
            }

            if ($existing instanceof EventRegistration) {
                $existing->forceFill([
                    'status' => $status,
                    'waitlist_position' => $waitlistPosition,
                    'registered_at' => $now,
                    'cancelled_at' => null,
                    'attendance_recorded_at' => null,
                    'attendance_recorded_by_user_id' => null,
                ])->save();
                $registration = $existing;
            } else {
                $registration = EventRegistration::query()->create([
                    'alliance_id' => $alliance->id,
                    'occurrence_id' => $occurrence->id,
                    'membership_id' => $membership->id,
                    'status' => $status,
                    'waitlist_position' => $waitlistPosition,
                    'registered_at' => $now,
                ]);
            }

            $this->audit->record(
                'event.registration.created',
                actor: $actor,
                subject: $registration,
                alliance: $alliance,
                metadata: ['status' => $status->value],
            );

            $this->outbox->record('event.registration.created', $alliance, $registration, [
                'occurrence_id' => $occurrence->id,
                'membership_id' => $membership->id,
                'status' => $status->value,
            ]);

            return $registration;
        });
    }
}
