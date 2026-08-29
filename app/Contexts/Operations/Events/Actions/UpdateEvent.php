<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Actions;

use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventSchedulePolicyResolver;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Events\Services\RecurrenceCalculator;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateEvent
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private EventSchedulePolicyResolver $schedulePolicy,
        private RecurrenceCalculator $recurrence,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string, mixed>|null $settings */
    public function handle(
        string $actorPlayerId,
        string $eventId,
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
    ): void {
        DB::transaction(function () use (
            $actorPlayerId,
            $eventId,
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
        ): void {
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, $eventId, true);
            $this->mutations->authorizeManager($context);
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
                'updated_by_player_id' => $currentActor->playerId,
            ])->save();

            if ($scheduleChanged) {
                $this->reconcileFutureOccurrences($locked, $occurrenceStarts, $duration);
            }

            $metadata = [
                'scope' => $locked->scope->value,
                'target_id' => $target->targetId,
                'before' => $before,
                'schedule_changed' => $scheduleChanged,
                'actor_player_id' => $currentActor->playerId,
            ];

            $this->audit->record('event.updated', $currentActor, $locked, metadata: $metadata);
            $this->outbox->record(
                'event.updated',
                $target->allianceId,
                $locked,
                $metadata,
                partitionKey: $target->partitionKey(),
            );

        });
    }

    /**
     * Reconcile future occurrence identities instead of cancelling and recreating
     * rows whose start time is unchanged. Occurrence-local participation, poll,
     * roster and result state therefore remains attached to the same occurrence.
     *
     * @param  list<CarbonImmutable>  $occurrenceStarts
     */
    private function reconcileFutureOccurrences(
        Event $event,
        array $occurrenceStarts,
        int $durationMinutes,
    ): void {
        $now = CarbonImmutable::now('UTC');
        $desiredStarts = collect($occurrenceStarts)
            ->map(static fn (CarbonImmutable $start): CarbonImmutable => $start->utc())
            ->filter(static fn (CarbonImmutable $start): bool => ! $start->lessThan($now))
            ->values();

        $future = EventOccurrence::query()
            ->where('event_id', $event->id)
            ->where('starts_at', '>=', $now)
            ->orderBy('starts_at')
            ->lockForUpdate()
            ->get();

        foreach ($future as $existing) {
            $matchesDesiredStart = $desiredStarts->contains(
                static fn (CarbonImmutable $start): bool => CarbonImmutable::instance($existing->starts_at)->utc()->equalTo($start),
            );

            if (! $matchesDesiredStart && $existing->status === EventOccurrenceStatus::Scheduled) {
                $existing->forceFill([
                    'status' => EventOccurrenceStatus::Cancelled,
                ])->save();
            }
        }

        foreach ($desiredStarts as $start) {
            $existing = $future->first(
                static fn (EventOccurrence $candidate): bool => CarbonImmutable::instance($candidate->starts_at)->utc()->equalTo($start),
            );

            if ($existing instanceof EventOccurrence) {
                $existing->forceFill([
                    'ends_at' => $start->addMinutes($durationMinutes),
                    'status' => EventOccurrenceStatus::Scheduled,
                ])->save();

                continue;
            }

            EventOccurrence::query()->create([
                'event_id' => $event->id,
                'starts_at' => $start,
                'ends_at' => $start->addMinutes($durationMinutes),
                'status' => EventOccurrenceStatus::Scheduled,
            ]);
        }
    }

    private function sameInstant(mixed $stored, ?CarbonImmutable $resolved): bool
    {
        if ($stored === null || $resolved === null) {
            return $stored === null && $resolved === null;
        }

        return CarbonImmutable::instance($stored)->utc()->equalTo($resolved->utc());
    }
}
