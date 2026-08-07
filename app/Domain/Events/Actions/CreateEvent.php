<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Events\Services\EventOutbox;
use App\Domain\Events\Services\RecurrenceCalculator;
use App\Domain\Identity\Models\User;
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
        ?CarbonImmutable $firstLocalStart,
        int $durationMinutes,
        ?int $capacity = null,
        ?int $registrationOpensMinutesBefore = null,
        int $registrationClosesMinutesBefore = 0,
        RecurrenceFrequency $frequency = RecurrenceFrequency::None,
        int $recurrenceInterval = 1,
        ?CarbonImmutable $recurrenceUntilLocal = null,
        ?string $instructions = null,
        bool $publish = true,
        ?EventTemplate $template = null,
    ): Event {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::EventManage)) {
            throw new AuthorizationException('You are not allowed to manage alliance events.');
        }

        if (! $firstLocalStart instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Event start date is required.');
        }

        if ($template !== null && $template->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The event template belongs to another alliance.');
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

        if ($localUntil !== null && $localUntil->lessThan($localStart)) {
            throw new InvalidArgumentException('Recurrence end must not be earlier than the first occurrence.');
        }

        $occurrenceStarts = $this->recurrence->calculate(
            $localStart,
            $frequency,
            $recurrenceInterval,
            $localUntil,
        );

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $template,
            $title,
            $instructions,
            $localStart,
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
                'template_id' => $template?->id,
                'title' => trim($title),
                'instructions' => $instructions === null ? null : trim($instructions),
                'timezone' => $alliance->timezone,
                'starts_at' => $localStart->utc(),
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
                    'template_id' => $template?->id,
                    'recurrence' => $frequency->value,
                    'occurrence_count' => count($occurrenceStarts),
                    'published' => $publish,
                ],
            );

            $this->outbox->record('event.created', $alliance, $event, [
                'template_id' => $template?->id,
                'recurrence' => $frequency->value,
                'occurrence_count' => count($occurrenceStarts),
                'published' => $publish,
            ]);

            return $event->load('occurrences');
        });
    }
}
