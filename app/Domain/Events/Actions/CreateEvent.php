<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Events\Models\EventTypeScope;
use App\Domain\Events\Services\EventAuthorization;
use App\Domain\Events\Services\EventPhaseService;
use App\Domain\Events\Services\EventPollTemplateMaterializer;
use App\Domain\Events\Services\EventRosterService;
use App\Domain\Events\Services\EventSchedulePolicyResolver;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Events\Services\EventTypeDefaultsResolver;
use App\Domain\Events\Services\RecurrenceCalculator;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateEvent
{
    public function __construct(
        private EventAuthorization $authorization,
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
        $scope = $this->targets->scopeFor($target);
        if ($configuration->scope !== $scope) {
            throw new InvalidArgumentException('Event type scope does not match the selected target.');
        }

        $storedDefaults = $this->defaults->resolve($configuration);
        $scheduleDefaults = $template === null ? $storedDefaults : [
            'recurrence_policy' => $template->recurrence_policy->value,
            'default_recurrence_frequency' => $template->recurrence_frequency->value,
            'default_recurrence_interval' => $template->recurrence_interval,
            'minimum_repeat_interval_minutes' => $template->minimum_repeat_interval_minutes,
        ];
        $permission = PermissionKey::from((string) $configuration->create_permission_key);
        $this->authorization->authorize($actor, $scope, $target, $permission);

        $duration = $durationMinutes ?? $storedDefaults['default_duration_minutes'];
        if (! is_int($duration) || $duration < 1 || $duration > 10080) {
            throw new InvalidArgumentException('Event duration is required and must be between 1 and 10080 minutes.');
        }

        $resolvedCapacity = $capacity ?? $storedDefaults['default_capacity'];
        if ($resolvedCapacity !== null && ((int) $resolvedCapacity < 1 || (int) $resolvedCapacity > 100000)) {
            throw new InvalidArgumentException('Event capacity must be between 1 and 100000 when provided.');
        }

        $opens = $registrationOpensMinutesBefore ?? $storedDefaults['default_registration_opens_minutes_before'];
        $closes = $registrationClosesMinutesBefore ?? $storedDefaults['default_registration_closes_minutes_before'];
        if ($opens !== null && $closes !== null && (int) $opens < (int) $closes) {
            throw new InvalidArgumentException('Registration must open before it closes.');
        }

        $resolvedSchedule = $this->schedulePolicy->resolve($scheduleDefaults, $frequency, $recurrenceInterval);
        if ($resolvedSchedule['frequency'] === RecurrenceFrequency::None && $recurrenceUntilLocal !== null) {
            throw new InvalidArgumentException('A non-recurring event cannot have a recurrence end date.');
        }

        $timezone = $this->targets->defaultTimezone($actor, $target);
        $localStart = $firstLocalStart->setTimezone($timezone);
        $localUntil = $recurrenceUntilLocal?->setTimezone($timezone);
        $occurrenceStarts = $this->recurrence->calculate(
            $localStart,
            $resolvedSchedule['frequency'],
            $resolvedSchedule['interval'],
            $localUntil,
        );

        $targetColumns = $this->targets->columnsFor($target);
        if ($template !== null) {
            if ((string) $template->event_type_scope_id !== (string) $configuration->id
                || $template->scope !== $scope
                || $this->targets->columnsFor($this->targets->forTemplate($template)) !== $targetColumns) {
                throw new InvalidArgumentException('Event template does not match the selected event type scope and target.');
            }
        }
        $baseSettings = $template?->settings ?? $storedDefaults['default_settings'];
        $resolvedSettings = array_replace_recursive($baseSettings, $settings);

        return DB::transaction(function () use (
            $actor,
            $configuration,
            $scope,
            $target,
            $targetColumns,
            $title,
            $instructions,
            $timezone,
            $storedDefaults,
            $scheduleDefaults,
            $localStart,
            $duration,
            $resolvedCapacity,
            $opens,
            $closes,
            $resolvedSchedule,
            $localUntil,
            $resolvedSettings,
            $publish,
            $occurrenceStarts,
            $template,
        ): Event {
            $event = Event::query()->create([
                'event_type_scope_id' => $configuration->id,
                'event_type_id' => $configuration->event_type_id,
                'scope' => $scope,
                ...$targetColumns,
                'template_id' => $template?->id,
                'title' => $title === null || trim($title) === '' ? null : trim($title),
                'instructions' => $instructions === null || trim($instructions) === '' ? null : trim($instructions),
                'timezone' => $timezone,
                'schedule_source' => $template?->schedule_source ?? $storedDefaults['schedule_source'],
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
                'created_by_player_id' => $actor->id,
                'updated_by_player_id' => $actor->id,
            ]);

            foreach ($occurrenceStarts as $occurrenceLocalStart) {
                $occurrence = EventOccurrence::query()->create([
                    'event_id' => $event->id,
                    'starts_at' => $occurrenceLocalStart->utc(),
                    'ends_at' => $occurrenceLocalStart->addMinutes($duration)->utc(),
                    'status' => EventOccurrenceStatus::Scheduled,
                    'settings' => null,
                ]);
                $this->phases->materializeDefaults($occurrence, $actor);
                $this->polls->materializeDefaults($occurrence, $actor);
                $this->rosters->materializeDefaults($occurrence, $actor);
            }

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'scope' => $scope->value,
                'target_id' => (string) $target->id,
                'event_type_scope_id' => (string) $configuration->id,
                'occurrence_count' => count($occurrenceStarts),
                'published' => $publish,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record('event.created', $actor, $event, $alliance, $metadata);
            $this->outbox->record('event.created', $alliance?->id, $event, $metadata, partitionKey: $scope->value.':'.$target->id);

            return $event->refresh()->load(['eventType', 'typeScope.capabilities', 'occurrences']);
        });
    }
}
