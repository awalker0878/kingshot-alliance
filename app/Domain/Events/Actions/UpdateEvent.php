<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Events\Services\EventPhaseService;
use App\Domain\Events\Services\EventPollTemplateMaterializer;
use App\Domain\Events\Services\EventRosterService;
use App\Domain\Events\Services\EventSchedulePolicyResolver;
use App\Domain\Events\Services\RecurrenceCalculator;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Messaging\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateEvent
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventSchedulePolicyResolver $schedulePolicy,
        private RecurrenceCalculator $recurrence,
        private EventPhaseService $phases,
        private EventPollTemplateMaterializer $polls,
        private EventRosterService $rosters,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string, mixed>|null $settings */
    public function handle(
        Player $actor,
        Event $event,
        ?CarbonImmutable $firstLocalStart = null,
        ?string $title = null,
        ?string $instructions = null,
        ?int $durationMinutes = null,
        ?int $capacity = null,
        ?int $registrationOpensMinutesBefore = null,
        ?int $registrationClosesMinutesBefore = null,
        ?RecurrenceFrequency $frequency = null,
        ?int $recurrenceInterval = null,
        ?CarbonImmutable $recurrenceUntilLocal = null,
        ?array $settings = null,
    ): Event {
        return DB::transaction(function () use (
            $actor,
            $event,
            $firstLocalStart,
            $title,
            $instructions,
            $durationMinutes,
            $capacity,
            $registrationOpensMinutesBefore,
            $registrationClosesMinutesBefore,
            $frequency,
            $recurrenceInterval,
            $recurrenceUntilLocal,
            $settings,
        ): Event {
            $context = $this->mutations->requireManagerExclusive($actor, $event);
            $locked = $context->event;
            $target = $context->target;
            $currentActor = $context->actor;

            $scheduleDefaults = [
                'recurrence_policy' => $locked->recurrence_policy->value,
                'default_recurrence_frequency' => $locked->recurrence_frequency->value,
                'default_recurrence_interval' => $locked->recurrence_interval,
                'minimum_repeat_interval_minutes' => $locked->minimum_repeat_interval_minutes,
            ];
            $resolvedSchedule = $this->schedulePolicy->resolve(
                $scheduleDefaults,
                $frequency ?? $locked->recurrence_frequency,
                $recurrenceInterval ?? $locked->recurrence_interval,
            );

            $duration = $durationMinutes ?? $locked->duration_minutes;
            if ($duration < 1 || $duration > 10080) {
                throw new InvalidArgumentException('Event duration must be between 1 and 10080 minutes.');
            }

            $opens = $registrationOpensMinutesBefore ?? $locked->registration_opens_minutes_before;
            $closes = $registrationClosesMinutesBefore ?? $locked->registration_closes_minutes_before;
            if ($opens !== null && $closes !== null && $opens < $closes) {
                throw new InvalidArgumentException('Registration must open before it closes.');
            }

            $localStart = ($firstLocalStart ?? CarbonImmutable::instance($locked->starts_at))
                ->setTimezone($locked->timezone);
            $localUntil = $recurrenceUntilLocal?->setTimezone($locked->timezone)
                ?? ($locked->recurrence_until === null
                    ? null
                    : CarbonImmutable::instance($locked->recurrence_until)->setTimezone($locked->timezone));

            if ($resolvedSchedule['frequency'] === RecurrenceFrequency::None) {
                $localUntil = null;
            }

            $scheduleChanged = ! CarbonImmutable::instance($locked->starts_at)->utc()->equalTo($localStart->utc())
                || (int) $locked->duration_minutes !== (int) $duration
                || $locked->recurrence_frequency !== $resolvedSchedule['frequency']
                || (int) $locked->recurrence_interval !== (int) $resolvedSchedule['interval']
                || ! $this->sameInstant($locked->recurrence_until, $localUntil?->utc());

            $occurrenceStarts = $scheduleChanged
                ? $this->recurrence->calculate(
                    $localStart,
                    $resolvedSchedule['frequency'],
                    $resolvedSchedule['interval'],
                    $localUntil,
                )
                : [];

            $resolvedCapacity = $capacity ?? $locked->capacity;
            if ($resolvedCapacity !== null) {
                $maxRegistered = EventRegistration::query()
                    ->whereIn(
                        'occurrence_id',
                        EventOccurrence::query()->where('event_id', $locked->id)->select('id'),
                    )
                    ->where('status', 'registered')
                    ->selectRaw('occurrence_id, COUNT(*) AS aggregate')
                    ->groupBy('occurrence_id')
                    ->pluck('aggregate')
                    ->map(static fn ($count): int => (int) $count)
                    ->max() ?? 0;

                if ($maxRegistered > (int) $resolvedCapacity) {
                    throw new InvalidArgumentException('Event capacity cannot be lower than the current registered Player count.');
                }
            }

            $before = [
                'starts_at' => $locked->starts_at?->toIso8601String(),
                'duration_minutes' => $locked->duration_minutes,
                'recurrence_frequency' => $locked->recurrence_frequency->value,
                'recurrence_interval' => $locked->recurrence_interval,
            ];

            $locked->forceFill([
                'title' => $title === null ? $locked->title : (trim($title) === '' ? null : trim($title)),
                'instructions' => $instructions === null ? $locked->instructions : (trim($instructions) === '' ? null : trim($instructions)),
                'starts_at' => $localStart->utc(),
                'duration_minutes' => $duration,
                'capacity' => $resolvedCapacity,
                'registration_opens_minutes_before' => $opens,
                'registration_closes_minutes_before' => $closes,
                'recurrence_frequency' => $resolvedSchedule['frequency'],
                'recurrence_interval' => $resolvedSchedule['interval'],
                'recurrence_until' => $localUntil?->utc(),
                'settings' => $settings === null ? $locked->settings : ($settings === [] ? null : $settings),
                'updated_by_player_id' => $currentActor->id,
            ])->save();

            if ($scheduleChanged) {
                EventOccurrence::query()
                    ->where('event_id', $locked->id)
                    ->where('status', EventOccurrenceStatus::Scheduled->value)
                    ->where('starts_at', '>=', now())
                    ->update([
                        'status' => EventOccurrenceStatus::Cancelled->value,
                        'updated_at' => now(),
                    ]);

                foreach ($occurrenceStarts as $occurrenceLocalStart) {
                    if ($occurrenceLocalStart->utc()->lessThan(now())) {
                        continue;
                    }

                    $replacement = EventOccurrence::query()->create([
                        'event_id' => $locked->id,
                        'starts_at' => $occurrenceLocalStart->utc(),
                        'ends_at' => $occurrenceLocalStart->addMinutes($duration)->utc(),
                        'status' => EventOccurrenceStatus::Scheduled,
                    ]);
                    $this->phases->materializeDefaults($replacement, $currentActor);
                    $this->polls->materializeDefaults($replacement, $currentActor);
                    $this->rosters->materializeDefaults($replacement, $currentActor);
                }
            }

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'scope' => $locked->scope->value,
                'target_id' => (string) $target->id,
                'before' => $before,
                'schedule_changed' => $scheduleChanged,
                'actor_player_id' => (string) $currentActor->id,
            ];

            $this->audit->record('event.updated', $currentActor, $locked, $alliance, $metadata);
            $this->outbox->record(
                'event.updated',
                $alliance?->id,
                $locked,
                $metadata,
                partitionKey: $locked->scope->value.':'.$target->id,
            );

            return $locked->refresh()->load(['eventType', 'typeScope.capabilities', 'occurrences']);
        });
    }

    private function sameInstant(mixed $stored, ?CarbonImmutable $resolved): bool
    {
        if ($stored === null || $resolved === null) {
            return $stored === null && $resolved === null;
        }

        return CarbonImmutable::instance($stored)->utc()->equalTo($resolved->utc());
    }
}
