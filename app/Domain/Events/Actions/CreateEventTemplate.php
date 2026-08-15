<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Events\Models\EventTypeScope;
use App\Domain\Events\Services\EventCreationMutationAuthority;
use App\Domain\Events\Services\EventSchedulePolicyResolver;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Events\Services\EventTypeDefaultsResolver;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateEventTemplate
{
    public function __construct(
        private EventCreationMutationAuthority $mutations,
        private EventTargetResolver $targets,
        private EventTypeDefaultsResolver $defaults,
        private EventSchedulePolicyResolver $schedulePolicy,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string, mixed> $settings */
    public function handle(
        Player $actor,
        EventTypeScope $configuration,
        Alliance|Kingdom|Player $target,
        string $name,
        ?string $instructions = null,
        ?int $durationMinutes = null,
        ?int $capacity = null,
        ?int $registrationOpensMinutesBefore = null,
        ?int $registrationClosesMinutesBefore = null,
        ?RecurrenceFrequency $frequency = null,
        ?int $recurrenceInterval = null,
        array $settings = [],
    ): EventTemplate {
        return DB::transaction(function () use (
            $actor,
            $configuration,
            $target,
            $name,
            $instructions,
            $durationMinutes,
            $capacity,
            $registrationOpensMinutesBefore,
            $registrationClosesMinutesBefore,
            $frequency,
            $recurrenceInterval,
            $settings,
        ): EventTemplate {
            $context = $this->mutations->requireManage($actor, $configuration, $target);
            $currentConfiguration = $context->typeScope;
            $currentTarget = $context->target;
            $currentActor = $context->actor;
            $scope = $this->targets->scopeFor($currentTarget);

            $storedDefaults = $this->defaults->resolve($currentConfiguration);
            $duration = $durationMinutes ?? $storedDefaults['default_duration_minutes'];
            if (! is_int($duration) || $duration < 1 || $duration > 10080) {
                throw new InvalidArgumentException('Template duration is required and must be between 1 and 10080 minutes.');
            }

            $resolvedCapacity = $capacity ?? $storedDefaults['default_capacity'];
            if ($resolvedCapacity !== null && ((int) $resolvedCapacity < 1 || (int) $resolvedCapacity > 100000)) {
                throw new InvalidArgumentException('Template capacity must be between 1 and 100000 when provided.');
            }

            $opens = $registrationOpensMinutesBefore ?? $storedDefaults['default_registration_opens_minutes_before'];
            $closes = $registrationClosesMinutesBefore ?? $storedDefaults['default_registration_closes_minutes_before'];
            if ($opens !== null && $closes !== null && (int) $opens < (int) $closes) {
                throw new InvalidArgumentException('Registration must open before it closes.');
            }

            $resolvedSchedule = $this->schedulePolicy->resolve($storedDefaults, $frequency, $recurrenceInterval);
            $targetColumns = $this->targets->columnsFor($currentTarget);
            $timezone = $this->targets->defaultTimezone($currentActor, $currentTarget);
            $resolvedSettings = array_replace_recursive($storedDefaults['default_settings'], $settings);

            $template = EventTemplate::query()->create([
                'event_type_scope_id' => $currentConfiguration->id,
                'event_type_id' => $currentConfiguration->event_type_id,
                'scope' => $scope,
                ...$targetColumns,
                'name' => trim($name),
                'instructions' => $instructions === null || trim($instructions) === '' ? null : trim($instructions),
                'timezone' => $timezone,
                'schedule_source' => $storedDefaults['schedule_source'],
                'recurrence_policy' => $storedDefaults['recurrence_policy'],
                'minimum_repeat_interval_minutes' => $storedDefaults['minimum_repeat_interval_minutes'],
                'duration_minutes' => $duration,
                'capacity' => $resolvedCapacity,
                'registration_opens_minutes_before' => $opens,
                'registration_closes_minutes_before' => $closes,
                'recurrence_frequency' => $resolvedSchedule['frequency'],
                'recurrence_interval' => $resolvedSchedule['interval'],
                'settings' => $resolvedSettings === [] ? null : $resolvedSettings,
                'is_active' => true,
                'created_by_player_id' => $currentActor->id,
                'updated_by_player_id' => $currentActor->id,
            ]);

            $alliance = $currentTarget instanceof Alliance ? $currentTarget : null;
            $metadata = [
                'scope' => $scope->value,
                'target_id' => (string) $currentTarget->id,
                'event_type_scope_id' => (string) $currentConfiguration->id,
                'actor_player_id' => (string) $currentActor->id,
            ];
            $this->audit->record('event.template.created', $currentActor, $template, $alliance, $metadata);
            $this->outbox->record(
                'event.template.created',
                $alliance?->id,
                $template,
                $metadata,
                partitionKey: $scope->value.':'.$currentTarget->id,
            );

            return $template->refresh()->load(['eventType', 'typeScope.capabilities']);
        });
    }
}
