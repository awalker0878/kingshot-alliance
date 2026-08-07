<?php

declare(strict_types=1);

namespace App\Application\Events;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateEvent
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private RecurrenceCalculator $recurrence,
        private AuditRecorder $audit,
        private EventOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        string $title,
        CarbonImmutable $firstLocalStart,
        int $durationMinutes,
        ?int $capacity = null,
        ?int $registrationOpensMinutesBefore = null,
        int $registrationClosesMinutesBefore = 0,
        RecurrenceFrequency $frequency = RecurrenceFrequency::None,
        int $recurrenceInterval = 1,
        ?CarbonImmutable $recurrenceUntilLocal = null,
        ?string $instructions = null,
        bool $publish = true,
    ): Event {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::EventManage)) {
            throw new AuthorizationException('You are not allowed to manage alliance events.');
        }

        if ($durationMinutes < 1 || $durationMinutes > 1440) {
            throw new InvalidArgumentException('Event duration must be between 1 and 1440 minutes.');
        }

        if ($capacity !== null && $capacity < 1) {
            throw new InvalidArgumentException('Event capacity must be at least one when provided.');
        }

        if ($registrationOpensMinutesBefore !== null && $registrationOpensMinutesBefore < $registrationClosesMinutesBefore) {
            throw new InvalidArgumentException('Registration must open before it closes.');
        }

        $localStart = $firstLocalStart->setTimezone($alliance->timezone);
        $localUntil = $recurrenceUntilLocal?->setTimezone($alliance->timezone);
        $occurrenceStarts = $this->recurrence->calculate(
            $localStart,
            $frequency,
            $recurrenceInterval,
            $localUntil,
        );

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $title,
            $instructions,
            $durationMinutes,
            $capacity,
            $registrationOpensMinutesBefore,
            $registrationClosesMinutesBefore,
            $frequency,
            $recurrenceInterval,
            $localUntil,
            $occurrenceStarts,
            $publish,
        ): Event {
            $event = Event::query()->create([
                'alliance_id' => $alliance->id,
                'title' => trim($title),
                'instructions' => $instructions === null ? null : trim($instructions),
                'timezone' => $alliance->timezone,
                'duration_minutes' => $durationMinutes,
                'capacity' => $capacity,
                'registration_opens_minutes_before' => $registrationOpensMinutesBefore,
                'registration_closes_minutes_before' => $registrationClosesMinutesBefore,
                'recurrence_frequency' => $frequency,
                'recurrence_interval' => $recurrenceInterval,
                'recurrence_weekdays' => null,
                'recurrence_until' => $localUntil?->utc(),
                'status' => $publish ? EventStatus::Published : EventStatus::Draft,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            foreach ($occurrenceStarts as $occurrenceLocalStart) {
                $registrationOpensAt = $registrationOpensMinutesBefore === null
                    ? null
                    : $occurrenceLocalStart->subMinutes($registrationOpensMinutesBefore)->utc();
                $registrationClosesAt = $occurrenceLocalStart->subMinutes($registrationClosesMinutesBefore)->utc();

                EventOccurrence::query()->create([
                    'alliance_id' => $alliance->id,
                    'event_id' => $event->id,
                    'starts_at' => $occurrenceLocalStart->utc(),
                    'ends_at' => $occurrenceLocalStart->addMinutes($durationMinutes)->utc(),
                    'registration_opens_at' => $registrationOpensAt,
                    'registration_closes_at' => $registrationClosesAt,
                    'capacity' => $capacity,
                    'status' => EventOccurrenceStatus::Scheduled,
                ]);
            }

            $this->audit->record(
                'event.created',
                actor: $actor,
                subject: $event,
                alliance: $alliance,
                metadata: [
                    'recurrence' => $frequency->value,
                    'occurrence_count' => count($occurrenceStarts),
                    'published' => $publish,
                ],
            );

            $this->outbox->record('event.created', $alliance, $event, [
                'recurrence' => $frequency->value,
                'occurrence_count' => count($occurrenceStarts),
                'published' => $publish,
            ]);

            return $event->load('occurrences');
        });
    }
}
