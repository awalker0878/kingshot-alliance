<?php

declare(strict_types=1);

namespace App\Contexts\Platform\EventAdministration\Actions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\EventCore\Enums\EventScheduleSource;
use App\Contexts\Operations\EventCore\Enums\RecurrenceFrequency;
use App\Contexts\Operations\EventCore\Models\EventTypeScope;
use App\Contexts\Platform\Access\Services\PlatformMutationAuthority;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateEventTypeScope
{
    public function __construct(
        private PlatformMutationAuthority $platformMutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  list<EventCapability>  $capabilities
     * @param  array<string, mixed>  $defaultSettings
     */
    public function handle(
        User $actor,
        EventTypeScope $configuration,
        bool $isActive,
        ?int $defaultDurationMinutes,
        ?int $defaultCapacity,
        EventScheduleSource $scheduleSource,
        EventRecurrencePolicy $recurrencePolicy,
        RecurrenceFrequency $defaultRecurrenceFrequency,
        int $defaultRecurrenceInterval,
        ?int $minimumRepeatIntervalMinutes,
        ?int $defaultRegistrationOpensMinutesBefore,
        ?int $defaultRegistrationClosesMinutesBefore,
        ?string $defaultInstructionsKey,
        array $defaultSettings,
        array $capabilities,
    ): EventTypeScope {
        if ($recurrencePolicy === EventRecurrencePolicy::Disabled) {
            $defaultRecurrenceFrequency = RecurrenceFrequency::None;
            $defaultRecurrenceInterval = 1;
            $minimumRepeatIntervalMinutes = null;
        } elseif ($recurrencePolicy === EventRecurrencePolicy::FixedInterval) {
            if ($defaultRecurrenceFrequency === RecurrenceFrequency::None) {
                throw new InvalidArgumentException('Fixed recurrence requires a recurrence frequency.');
            }

            if ($minimumRepeatIntervalMinutes === null || $minimumRepeatIntervalMinutes < 1) {
                throw new InvalidArgumentException('Fixed recurrence requires a minimum repeat interval.');
            }
        }

        if ($defaultRecurrenceInterval < 1) {
            throw new InvalidArgumentException('Recurrence interval must be at least one.');
        }

        return DB::transaction(function () use (
            $actor,
            $configuration,
            $isActive,
            $defaultDurationMinutes,
            $defaultCapacity,
            $scheduleSource,
            $recurrencePolicy,
            $defaultRecurrenceFrequency,
            $defaultRecurrenceInterval,
            $minimumRepeatIntervalMinutes,
            $defaultRegistrationOpensMinutesBefore,
            $defaultRegistrationClosesMinutesBefore,
            $defaultInstructionsKey,
            $defaultSettings,
            $capabilities,
        ): EventTypeScope {
            $context = $this->platformMutations->require($actor);

            $locked = EventTypeScope::query()
                ->whereKey($configuration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'is_active' => $isActive,
                'default_duration_minutes' => $defaultDurationMinutes,
                'default_capacity' => $defaultCapacity,
                'schedule_source' => $scheduleSource,
                'recurrence_policy' => $recurrencePolicy,
                'default_recurrence_frequency' => $defaultRecurrenceFrequency,
                'default_recurrence_interval' => $defaultRecurrenceInterval,
                'minimum_repeat_interval_minutes' => $minimumRepeatIntervalMinutes,
                'default_registration_opens_minutes_before' => $defaultRegistrationOpensMinutesBefore,
                'default_registration_closes_minutes_before' => $defaultRegistrationClosesMinutesBefore,
                'default_instructions_key' => $defaultInstructionsKey === null || trim($defaultInstructionsKey) === ''
                    ? null
                    : trim($defaultInstructionsKey),
                'default_settings' => $defaultSettings === [] ? null : $defaultSettings,
            ])->save();

            $wanted = array_values(array_unique(array_map(
                static fn (EventCapability $capability): string => $capability->value,
                $capabilities,
            )));

            $locked->capabilities()->whereNotIn('capability', $wanted)->delete();
            foreach ($wanted as $capability) {
                $locked->capabilities()->firstOrCreate([
                    'capability' => $capability,
                ], [
                    'configuration' => null,
                ]);
            }

            $eventType = $locked->eventType()->firstOrFail();
            $metadata = [
                'event_type_id' => $eventType->id,
                'scope' => $locked->scope->value,
                'is_active' => $isActive,
                'schedule_source' => $scheduleSource->value,
                'recurrence_policy' => $recurrencePolicy->value,
                'capabilities' => $wanted,
            ];

            $this->audit->record(
                event: 'event-type.scope.updated',
                actor: $context->actor,
                subject: $locked,
                metadata: $metadata,
            );
            $this->outbox->record('event-type.scope.updated', null, $locked, $metadata);

            return $locked->refresh()->load(['eventType', 'capabilities']);
        });
    }
}
