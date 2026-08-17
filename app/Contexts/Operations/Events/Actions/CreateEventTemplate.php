<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Actions;

use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Events\Models\EventTemplate;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventSchedulePolicyResolver;
use App\Contexts\Operations\Events\Services\EventTypeDefaultsResolver;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class CreateEventTemplate
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private EventTypeDefaultsResolver $defaults,
        private EventSchedulePolicyResolver $schedulePolicy,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string, mixed> $settings */
    public function handle(
        string $actorPlayerId,
        string $configurationId,
        EventScope $scope,
        string $targetId,
        string $name,
        ?string $instructions = null,
        ?int $durationMinutes = null,
        ?int $capacity = null,
        ?int $registrationOpensMinutesBefore = null,
        ?int $registrationClosesMinutesBefore = null,
        ?RecurrenceFrequency $frequency = null,
        ?int $recurrenceInterval = null,
        array $settings = [],
    ): void {
        DB::transaction(function () use (
            $actorPlayerId,
            $configurationId,
            $scope,
            $targetId,
            $name,
            $instructions,
            $durationMinutes,
            $capacity,
            $registrationOpensMinutesBefore,
            $registrationClosesMinutesBefore,
            $frequency,
            $recurrenceInterval,
            $settings,
        ): void {
            $context = $this->eventWriteState->lockCreationScope($actorPlayerId, $configurationId, $scope, $targetId);
            $this->mutations->authorizeCreation($context, true);

            $defaults = $this->defaults->resolve($context->typeScope);
            $duration = $durationMinutes ?? $defaults['default_duration_minutes'];
            if (! is_int($duration) || $duration < 1 || $duration > 10080) {
                throw new InvalidArgumentException('Template duration is required and must be between 1 and 10080 minutes.');
            }

            $resolvedCapacity = $capacity ?? $defaults['default_capacity'];
            if ($resolvedCapacity !== null && ((int) $resolvedCapacity < 1 || (int) $resolvedCapacity > 100000)) {
                throw new InvalidArgumentException('Template capacity must be between 1 and 100000 when provided.');
            }

            $opens = $registrationOpensMinutesBefore ?? $defaults['default_registration_opens_minutes_before'];
            $closes = $registrationClosesMinutesBefore ?? $defaults['default_registration_closes_minutes_before'];
            if ($opens !== null && $closes !== null && (int) $opens < (int) $closes) {
                throw new InvalidArgumentException('Registration must open before it closes.');
            }

            $resolvedSchedule = $this->schedulePolicy->resolve($defaults, $frequency, $recurrenceInterval);
            $resolvedSettings = array_replace_recursive($defaults['default_settings'], $settings);

            $template = EventTemplate::query()->create([
                'event_type_scope_id' => $context->typeScope->id,
                'event_type_id' => $context->typeScope->event_type_id,
                'scope' => $scope,
                ...$context->target->targetColumns(),
                'name' => trim($name),
                'instructions' => $instructions === null || trim($instructions) === '' ? null : trim($instructions),
                'timezone' => $context->target->timezone,
                'schedule_source' => $defaults['schedule_source'],
                'recurrence_policy' => $defaults['recurrence_policy'],
                'minimum_repeat_interval_minutes' => $defaults['minimum_repeat_interval_minutes'],
                'duration_minutes' => $duration,
                'capacity' => $resolvedCapacity,
                'registration_opens_minutes_before' => $opens,
                'registration_closes_minutes_before' => $closes,
                'recurrence_frequency' => $resolvedSchedule['frequency'],
                'recurrence_interval' => $resolvedSchedule['interval'],
                'settings' => $resolvedSettings === [] ? null : $resolvedSettings,
                'is_active' => true,
                'created_by_player_id' => $context->actor->playerId,
                'updated_by_player_id' => $context->actor->playerId,
            ]);

            $metadata = [
                'scope' => $scope->value,
                'target_id' => $context->target->targetId,
                'event_type_scope_id' => (string) $context->typeScope->id,
                'actor_player_id' => $context->actor->playerId,
            ];
            $this->audit->record('event.template.created', $context->actor, $template, metadata: $metadata);
            $this->outbox->record(
                'event.template.created',
                $context->target->allianceId,
                $template,
                $metadata,
                partitionKey: $context->target->partitionKey(),
            );
        });
    }
}
