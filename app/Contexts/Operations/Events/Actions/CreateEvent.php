<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Actions;

use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventStatus;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTemplate;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventPhaseService;
use App\Contexts\Operations\Events\Services\EventSchedulePolicyResolver;
use App\Contexts\Operations\Events\Services\EventTypeDefaultsResolver;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Events\Services\RecurrenceCalculator;
use App\Contexts\Operations\Events\ValueObjects\CreatedEvent;
use App\Contexts\Operations\Polls\Services\EventPollTemplateMaterializer;
use App\Contexts\Operations\Rosters\Services\EventRosterService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class CreateEvent
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private EventTypeDefaultsResolver $defaults,
        private EventSchedulePolicyResolver $schedulePolicy,
        private RecurrenceCalculator $recurrence,
        private EventPhaseService $phases,
        private EventPollTemplateMaterializer $polls,
        private EventRosterService $rosters,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string, mixed> $settings */
    public function handle(
        string $actorPlayerId,
        string $configurationId,
        EventScope $scope,
        string $targetId,
        CarbonImmutable $firstLocalStart,
        ?string $title = null,
        ?string $instructions = null,
        ?int $durationMinutes = null,
        ?int $capacity = null,
        ?int $registrationOpensMinutesBefore = null,
        ?int $registrationClosesMinutesBefore = null,
        ?RecurrenceFrequency $frequency = null,
        ?int $recurrenceInterval = null,
        ?CarbonImmutable $recurrenceUntilLocal = null,
        array $settings = [],
        bool $publish = true,
        ?string $templateId = null,
    ): CreatedEvent {
        return DB::transaction(function () use (
            $actorPlayerId,
            $configurationId,
            $scope,
            $targetId,
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
            $publish,
            $templateId,
        ): CreatedEvent {
            $context = $this->eventWriteState->lockCreationScope($actorPlayerId, $configurationId, $scope, $targetId);
            $this->mutations->authorizeCreation($context);

            $configuration = $context->typeScope;
            $target = $context->target;
            $storedDefaults = $this->defaults->resolve($configuration);
            $template = $templateId === null
                ? null
                : EventTemplate::query()->whereKey($templateId)->where('is_active', true)->sharedLock()->firstOrFail();

            $scheduleDefaults = $template === null ? $storedDefaults : [
                'recurrence_policy' => $template->recurrencePolicyEnum()->value,
                'default_recurrence_frequency' => $template->recurrenceFrequencyEnum()->value,
                'default_recurrence_interval' => $template->recurrence_interval,
                'minimum_repeat_interval_minutes' => $template->minimum_repeat_interval_minutes,
            ];

            $duration = $durationMinutes ?? ($template->duration_minutes ?? $storedDefaults['default_duration_minutes']);
            if (! is_int($duration) || $duration < 1 || $duration > 10080) {
                throw new InvalidArgumentException('Event duration is required and must be between 1 and 10080 minutes.');
            }

            $resolvedCapacity = $capacity ?? ($template->capacity ?? $storedDefaults['default_capacity']);
            if ($resolvedCapacity !== null && ((int) $resolvedCapacity < 1 || (int) $resolvedCapacity > 100000)) {
                throw new InvalidArgumentException('Event capacity must be between 1 and 100000 when provided.');
            }

            $opens = $registrationOpensMinutesBefore ?? ($template->registration_opens_minutes_before ?? $storedDefaults['default_registration_opens_minutes_before']);
            $closes = $registrationClosesMinutesBefore ?? ($template->registration_closes_minutes_before ?? $storedDefaults['default_registration_closes_minutes_before']);
            if ($opens !== null && $closes !== null && (int) $opens < (int) $closes) {
                throw new InvalidArgumentException('Registration must open before it closes.');
            }

            $resolvedFrequency = $frequency ?? $template?->recurrenceFrequencyEnum();
            $resolvedInterval = $recurrenceInterval ?? $template?->recurrence_interval;
            $resolvedSchedule = $this->schedulePolicy->resolve($scheduleDefaults, $resolvedFrequency, $resolvedInterval);
            if ($resolvedSchedule['frequency'] === RecurrenceFrequency::None && $recurrenceUntilLocal !== null) {
                throw new InvalidArgumentException('A non-recurring event cannot have a recurrence end date.');
            }

            $localStart = $firstLocalStart->setTimezone($target->timezone);
            $localUntil = $recurrenceUntilLocal?->setTimezone($target->timezone);
            $occurrenceStarts = $this->recurrence->calculate(
                $localStart,
                $resolvedSchedule['frequency'],
                $resolvedSchedule['interval'],
                $localUntil,
            );

            $targetColumns = $target->targetColumns();
            if ($template !== null) {
                $templateColumns = [
                    'alliance_id' => $template->alliance_id === null ? null : (string) $template->alliance_id,
                    'kingdom_id' => $template->kingdom_id === null ? null : (string) $template->kingdom_id,
                    'player_id' => $template->player_id === null ? null : (string) $template->player_id,
                ];
                if ((string) $template->event_type_scope_id !== (string) $configuration->id
                    || $template->scopeEnum() !== $scope
                    || $templateColumns !== $targetColumns) {
                    throw new InvalidArgumentException('Event template does not match the selected event type scope and target.');
                }
            }

            $baseSettings = $template->settings ?? $storedDefaults['default_settings'];
            $resolvedSettings = array_replace_recursive($baseSettings, $settings);
            $resolvedInstructions = $instructions ?? $template?->instructions;

            $event = Event::query()->create([
                'event_type_scope_id' => $configuration->id,
                'event_type_id' => $configuration->event_type_id,
                'scope' => $scope,
                ...$targetColumns,
                ...$target->historicalSnapshot(),
                'template_id' => $template?->id,
                'title' => $title === null || trim($title) === '' ? null : trim($title),
                'instructions' => $resolvedInstructions === null || trim($resolvedInstructions) === '' ? null : trim($resolvedInstructions),
                'timezone' => $target->timezone,
                'schedule_source' => $template?->scheduleSourceEnum() ?? $storedDefaults['schedule_source'],
                'recurrence_policy' => $scheduleDefaults['recurrence_policy'],
                'minimum_repeat_interval_minutes' => $scheduleDefaults['minimum_repeat_interval_minutes'],
                'starts_at' => $localStart->utc(),
                'duration_minutes' => $duration,
                'capacity' => $resolvedCapacity,
                'registration_opens_minutes_before' => $opens,
                'registration_closes_minutes_before' => $closes,
                'recurrence_frequency' => $resolvedSchedule['frequency'],
                'recurrence_interval' => $resolvedSchedule['interval'],
                'recurrence_until' => $localUntil?->utc(),
                'settings' => $resolvedSettings === [] ? null : $resolvedSettings,
                'status' => $publish ? EventStatus::Published : EventStatus::Draft,
                'created_by_player_id' => $context->actor->playerId,
                'updated_by_player_id' => $context->actor->playerId,
            ]);

            $firstOccurrenceId = null;
            foreach ($occurrenceStarts as $occurrenceLocalStart) {
                $occurrence = EventOccurrence::query()->create([
                    'event_id' => $event->id,
                    'starts_at' => $occurrenceLocalStart->utc(),
                    'ends_at' => $occurrenceLocalStart->addMinutes($duration)->utc(),
                    'status' => EventOccurrenceStatus::Scheduled,
                    'settings' => null,
                ]);
                $firstOccurrenceId ??= (string) $occurrence->id;
                $this->phases->materializeDefaults($occurrence, $context->actor->playerId);
                $this->polls->materializeDefaults($occurrence, $context->actor->playerId);
                $this->rosters->materializeDefaults($occurrence, $context->actor->playerId);
            }

            $metadata = [
                'scope' => $scope->value,
                'target_id' => $target->targetId,
                'event_type_scope_id' => (string) $configuration->id,
                'occurrence_count' => count($occurrenceStarts),
                'published' => $publish,
                'actor_player_id' => $context->actor->playerId,
            ];
            $this->audit->record('event.created', $context->actor, $event, metadata: $metadata);
            $this->outbox->record(
                'event.created',
                $target->allianceId,
                $event,
                $metadata,
                partitionKey: $target->partitionKey(),
            );

            return new CreatedEvent((string) $event->id, $firstOccurrenceId);
        });
    }
}
