<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Services\EventAuthorization;
use App\Domain\Events\Services\EventPhaseService;
use App\Domain\Events\Services\EventPollTemplateMaterializer;
use App\Domain\Events\Services\EventRosterService;
use App\Domain\Events\Services\EventSchedulePolicyResolver;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Events\Services\RecurrenceCalculator;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateEvent
{
    public function __construct(
        private EventAuthorization $authorization,
        private EventTargetResolver $targets,
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
        $event->loadMissing(['typeScope', 'occurrences']);
        $target = $this->targets->forEvent($event);
        $this->authorization->authorize(
            $actor,
            $event->scope,
            $target,
            PermissionKey::from((string) $event->typeScope->manage_permission_key),
        );

        $scheduleDefaults = [
            'recurrence_policy' => $event->recurrence_policy->value,
            'default_recurrence_frequency' => $event->recurrence_frequency->value,
            'default_recurrence_interval' => $event->recurrence_interval,
            'minimum_repeat_interval_minutes' => $event->minimum_repeat_interval_minutes,
        ];
        $resolvedSchedule = $this->schedulePolicy->resolve(
            $scheduleDefaults,
            $frequency ?? $event->recurrence_frequency,
            $recurrenceInterval ?? $event->recurrence_interval,
        );

        $duration = $durationMinutes ?? $event->duration_minutes;
        if ($duration < 1 || $duration > 10080) {
            throw new InvalidArgumentException('Event duration must be between 1 and 10080 minutes.');
        }

        $opens = $registrationOpensMinutesBefore ?? $event->registration_opens_minutes_before;
        $closes = $registrationClosesMinutesBefore ?? $event->registration_closes_minutes_before;
        if ($opens !== null && $closes !== null && $opens < $closes) {
            throw new InvalidArgumentException('Registration must open before it closes.');
        }

        $localStart = ($firstLocalStart ?? CarbonImmutable::instance($event->starts_at))->setTimezone($event->timezone);
        $localUntil = $recurrenceUntilLocal?->setTimezone($event->timezone)
            ?? ($event->recurrence_until === null ? null : CarbonImmutable::instance($event->recurrence_until)->setTimezone($event->timezone));
        if ($resolvedSchedule['frequency'] === RecurrenceFrequency::None) {
            $localUntil = null;
        }

        $scheduleChanged = ! CarbonImmutable::instance($event->starts_at)->utc()->equalTo($localStart->utc())
            || (int) $event->duration_minutes !== (int) $duration
            || $event->recurrence_frequency !== $resolvedSchedule['frequency']
            || (int) $event->recurrence_interval !== (int) $resolvedSchedule['interval']
            || ! $this->sameInstant($event->recurrence_until, $localUntil?->utc());

        $occurrenceStarts = $scheduleChanged
            ? $this->recurrence->calculate(
                $localStart,
                $resolvedSchedule['frequency'],
                $resolvedSchedule['interval'],
                $localUntil,
            )
            : [];

        return DB::transaction(function () use (
            $actor,
            $event,
            $target,
            $title,
            $instructions,
            $localStart,
            $duration,
            $capacity,
            $opens,
            $closes,
            $resolvedSchedule,
            $localUntil,
            $settings,
            $occurrenceStarts,
            $scheduleChanged,
        ): Event {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            $resolvedCapacity = $capacity ?? $locked->capacity;
            if ($resolvedCapacity !== null) {
                $maxRegistered = EventRegistration::query()
                    ->whereIn('occurrence_id', EventOccurrence::query()->where('event_id', $locked->id)->select('id'))
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
                'updated_by_player_id' => $actor->id,
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
                    $this->phases->materializeDefaults($replacement, $actor);
                    $this->polls->materializeDefaults($replacement, $actor);
                    $this->rosters->materializeDefaults($replacement, $actor);
                }
            }

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'scope' => $locked->scope->value,
                'target_id' => (string) $target->id,
                'before' => $before,
                'schedule_changed' => $scheduleChanged,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record('event.updated', $actor, $locked, $alliance, $metadata);
            $this->outbox->record('event.updated', $alliance?->id, $locked, $metadata, partitionKey: $locked->scope->value.':'.$target->id);

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
