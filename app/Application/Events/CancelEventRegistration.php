<?php

declare(strict_types=1);

namespace App\Application\Events;

use App\Application\Identity\AuditRecorder;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CancelEventRegistration
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
                throw new DomainException('An active alliance membership is required to cancel registration.');
            }

            $occurrence = EventOccurrence::query()
                ->where('id', $occurrenceId)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $registration = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('membership_id', $membership->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($registration->status === EventRegistrationStatus::Cancelled) {
                return $registration;
            }

            if (in_array($registration->status, [EventRegistrationStatus::Attended, EventRegistrationStatus::NoShow], true)) {
                throw new DomainException('Attendance has already been finalized for this occurrence.');
            }

            $wasRegistered = $registration->status === EventRegistrationStatus::Registered;
            $registration->forceFill([
                'status' => EventRegistrationStatus::Cancelled,
                'waitlist_position' => null,
                'cancelled_at' => now(),
            ])->save();

            $promoted = null;

            if ($wasRegistered) {
                $promoted = EventRegistration::query()
                    ->where('occurrence_id', $occurrence->id)
                    ->where('status', EventRegistrationStatus::Waitlisted->value)
                    ->orderBy('waitlist_position')
                    ->orderBy('registered_at')
                    ->lockForUpdate()
                    ->first();

                if ($promoted instanceof EventRegistration) {
                    $promoted->forceFill([
                        'status' => EventRegistrationStatus::Registered,
                        'waitlist_position' => null,
                    ])->save();
                }
            }

            $this->audit->record(
                'event.registration.cancelled',
                actor: $actor,
                subject: $registration,
                alliance: $alliance,
                metadata: ['promoted_registration_id' => $promoted?->id],
            );

            $this->outbox->record('event.registration.cancelled', $alliance, $registration, [
                'occurrence_id' => $occurrence->id,
                'membership_id' => $membership->id,
                'promoted_registration_id' => $promoted?->id,
            ]);

            if ($promoted instanceof EventRegistration) {
                $this->outbox->record('event.registration.promoted', $alliance, $promoted, [
                    'occurrence_id' => $occurrence->id,
                    'membership_id' => $promoted->membership_id,
                ]);
            }

            return $registration;
        });
    }
}
