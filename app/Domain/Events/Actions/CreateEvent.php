<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Events\Models\EventTypeScope;
use App\Domain\Events\Services\EventCreationMutationAuthority;
use App\Domain\Events\Services\EventPhaseService;
use App\Domain\Events\Services\EventPollTemplateMaterializer;
use App\Domain\Events\Services\EventRosterService;
use App\Domain\Events\Services\EventSchedulePolicyResolver;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Events\Services\EventTypeDefaultsResolver;
use App\Domain\Events\Services\RecurrenceCalculator;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateEvent
{
    public function __construct(
        private EventCreationMutationAuthority $mutations,
        private EventTargetResolver $targets,
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
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
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
        ?EventTemplate $template = null,
    ): Event {
        return DB::transaction(function () use (
            $actor,
            $configuration,
            $target,
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
            $template,
        ): Event {
            $context = $this->mutations->requireCreate($actor, $configuration, $target);
            $currentConfiguration = $context->typeScope;
            $currentTarget = $context->target;
            $currentActor = $context->actor;
            $scope = $this->targets->scopeFor($currentTarget);

            $storedDefaults = $this->defaults->resolve($currentConfiguration);
            $currentTemplate = $template === null
                ? null
                : EventTemplate::query()->whereKey($template->id)->sharedLock()->firstOrFail();

            $scheduleDefaults = $currentTemplate === null ? $storedDefaults : [
                'recurrence_policy' => $currentTemplate->recurrencePolicyEnum()->value,
                'default_recurrence_frequency' => $currentTemplate->recurrenceFrequencyEnum()->value,
                'default_recurrence_interval' => $currentTemplate->recurrence_interval,
                'minimum_repeat_interval_minutes' => $currentTemplate->minimum_repeat_interval_minutes,
            ];

            $duration = $durationMinutes
                ?? ($currentTemplate instanceof EventTemplate
                    ? (int) $currentTemplate->duration_minutes
                    : $storedDefaults['default_duration_minutes']);
            if (! is_int($duration) || $duration < 1 || $duration > 10080) {
                throw new InvalidArgumentException('Event duration is required and must be between 1 and 10080 minutes.');
            }

            $resolvedCapacity = $capacity ?? ($currentTemplate instanceof EventTemplate
                ? $currentTemplate->capacity
                : $storedDefaults['default_capacity']);
            if ($resolvedCapacity !== null && ((int) $resolvedCapacity < 1 || (int) $resolvedCapacity > 100000)) {
                throw new InvalidArgumentException('Event capacity must be between 1 and 100000 when provided.');
            }

            $opens = $registrationOpensMinutesBefore ?? ($currentTemplate instanceof EventTemplate
                ? $currentTemplate->registration_opens_minutes_before
                : $storedDefaults['default_registration_opens_minutes_before']);
            $closes = $registrationClosesMinutesBefore ?? ($currentTemplate instanceof EventTemplate
                ? $currentTemplate->registration_closes_minutes_before
                : $storedDefaults['default_registration_closes_minutes_before']);
            if ($opens !== null && $closes !== null && (int) $opens < (int) $closes) {
                throw new InvalidArgumentException('Registration must open before it closes.');
            }

            $resolvedFrequency = $frequency ?? $currentTemplate?->recurrenceFrequencyEnum();
            $resolvedInterval = $recurrenceInterval ?? $currentTemplate?->recurrence_interval;
            $resolvedSchedule = $this->schedulePolicy->resolve($scheduleDefaults, $resolvedFrequency, $resolvedInterval);
            if ($resolvedSchedule['frequency'] === RecurrenceFrequency::None && $recurrenceUntilLocal !== null) {
                throw new InvalidArgumentException('A non-recurring event cannot have a recurrence end date.');
            }

            $timezone = $this->targets->defaultTimezone($currentActor, $currentTarget);
            $localStart = $firstLocalStart->setTimezone($timezone);
            $localUntil = $recurrenceUntilLocal?->setTimezone($timezone);
            $occurrenceStarts = $this->recurrence->calculate(
                $localStart,
                $resolvedSchedule['frequency'],
                $resolvedSchedule['interval'],
                $localUntil,
            );

            $targetColumns = $this->targets->columnsFor($currentTarget);
            $targetSnapshot = $this->targets->historicalSnapshotFor($currentTarget);
            if ($currentTemplate !== null) {
                $templateColumns = [
                    'alliance_id' => $currentTemplate->alliance_id === null ? null : (string) $currentTemplate->alliance_id,
                    'kingdom_id' => $currentTemplate->kingdom_id === null ? null : (string) $currentTemplate->kingdom_id,
                    'player_id' => $currentTemplate->player_id === null ? null : (string) $currentTemplate->player_id,
                ];
                if ((string) $currentTemplate->event_type_scope_id !== (string) $currentConfiguration->id
                    || $currentTemplate->scopeEnum() !== $scope
                    || $templateColumns !== $targetColumns) {
                    throw new InvalidArgumentException('Event template does not match the selected event type scope and target.');
                }
            }

            $baseSettings = $currentTemplate instanceof EventTemplate
                ? ($currentTemplate->settings ?? [])
                : $storedDefaults['default_settings'];
            $resolvedSettings = array_replace_recursive($baseSettings, $settings);
            $resolvedInstructions = $instructions ?? $currentTemplate?->instructions;

            $event = Event::query()->create([
                'event_type_scope_id' => $currentConfiguration->id,
                'event_type_id' => $currentConfiguration->event_type_id,
                'scope' => $scope,
                ...$targetColumns,
                ...$targetSnapshot,
                'template_id' => $currentTemplate?->id,
                'title' => $title === null || trim($title) === '' ? null : trim($title),
                'instructions' => $resolvedInstructions === null || trim($resolvedInstructions) === '' ? null : trim($resolvedInstructions),
                'timezone' => $timezone,
                'schedule_source' => $currentTemplate instanceof EventTemplate
                    ? $currentTemplate->scheduleSourceEnum()
                    : $storedDefaults['schedule_source'],
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
                'created_by_player_id' => $currentActor->id,
                'updated_by_player_id' => $currentActor->id,
            ]);

            foreach ($occurrenceStarts as $occurrenceLocalStart) {
                $occurrence = EventOccurrence::query()->create([
                    'event_id' => $event->id,
                    'starts_at' => $occurrenceLocalStart->utc(),
                    'ends_at' => $occurrenceLocalStart->addMinutes($duration)->utc(),
                    'status' => EventOccurrenceStatus::Scheduled,
                    'settings' => null,
                ]);
                $this->phases->materializeDefaults($occurrence, $currentActor);
                $this->polls->materializeDefaults($occurrence, $currentActor);
                $this->rosters->materializeDefaults($occurrence, $currentActor);
            }

            $alliance = $currentTarget instanceof Alliance ? $currentTarget : null;
            $metadata = [
                'scope' => $scope->value,
                'target_id' => (string) $currentTarget->id,
                'event_type_scope_id' => (string) $currentConfiguration->id,
                'occurrence_count' => count($occurrenceStarts),
                'published' => $publish,
                'actor_player_id' => (string) $currentActor->id,
            ];
            $this->audit->record('event.created', $currentActor, $event, $alliance, $metadata);
            $this->outbox->record(
                'event.created',
                $alliance?->id,
                $event,
                $metadata,
                partitionKey: $scope->value.':'.$currentTarget->id,
            );

            return $event->refresh()->load(['eventType', 'typeScope.capabilities', 'occurrences']);
        });
    }
}
