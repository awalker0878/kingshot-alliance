<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Events\Models\EventTypeScope;
use App\Domain\Events\Services\EventAuthorization;
use App\Domain\Events\Services\EventSchedulePolicyResolver;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Events\Services\EventTypeDefaultsResolver;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateEventTemplate
{
    public function __construct(
        private EventAuthorization $authorization,
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
        $scope = $this->targets->scopeFor($target);
        if ($configuration->scope !== $scope) {
            throw new InvalidArgumentException('Event type scope does not match the selected target.');
        }
        $storedDefaults = $this->defaults->resolve($configuration);
        $permission = PermissionKey::from((string) $configuration->manage_permission_key);
        $this->authorization->authorize($actor, $scope, $target, $permission);

        $duration = $durationMinutes ?? $storedDefaults['default_duration_minutes'];
        if (! is_int($duration) || $duration < 1 || $duration > 10080) {
            throw new InvalidArgumentException('Template duration is required and must be between 1 and 10080 minutes.');
        }

        $resolvedSchedule = $this->schedulePolicy->resolve($storedDefaults, $frequency, $recurrenceInterval);
        $targetColumns = $this->targets->columnsFor($target);
        $timezone = $this->targets->defaultTimezone($actor, $target);
        $resolvedSettings = array_replace_recursive($storedDefaults['default_settings'], $settings);

        return DB::transaction(function () use (
            $actor,
            $configuration,
            $scope,
            $target,
            $targetColumns,
            $name,
            $instructions,
            $timezone,
            $duration,
            $capacity,
            $registrationOpensMinutesBefore,
            $registrationClosesMinutesBefore,
            $storedDefaults,
            $resolvedSchedule,
            $resolvedSettings,
        ): EventTemplate {
            $template = EventTemplate::query()->create([
                'event_type_scope_id' => $configuration->id,
                'event_type_id' => $configuration->event_type_id,
                'scope' => $scope,
                ...$targetColumns,
                'name' => trim($name),
                'instructions' => $instructions === null || trim($instructions) === '' ? null : trim($instructions),
                'timezone' => $timezone,
                'schedule_source' => $storedDefaults['schedule_source'],
                'recurrence_policy' => $storedDefaults['recurrence_policy'],
                'minimum_repeat_interval_minutes' => $storedDefaults['minimum_repeat_interval_minutes'],
                'duration_minutes' => $duration,
                'capacity' => $capacity ?? $storedDefaults['default_capacity'],
                'registration_opens_minutes_before' => $registrationOpensMinutesBefore ?? $storedDefaults['default_registration_opens_minutes_before'],
                'registration_closes_minutes_before' => $registrationClosesMinutesBefore ?? $storedDefaults['default_registration_closes_minutes_before'],
                'recurrence_frequency' => $resolvedSchedule['frequency'],
                'recurrence_interval' => $resolvedSchedule['interval'],
                'settings' => $resolvedSettings === [] ? null : $resolvedSettings,
                'is_active' => true,
                'created_by_player_id' => $actor->id,
                'updated_by_player_id' => $actor->id,
            ]);

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'scope' => $scope->value,
                'target_id' => (string) $target->id,
                'event_type_scope_id' => (string) $configuration->id,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record('event.template.created', $actor, $template, $alliance, $metadata);
            $this->outbox->record('event.template.created', $alliance?->id, $template, $metadata, partitionKey: $scope->value.':'.$target->id);

            return $template->refresh()->load(['eventType', 'typeScope.capabilities']);
        });
    }
}
