<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Actions;

use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\Events\Enums\EventScheduleSource;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Events\Models\EventTypeCapability;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Illuminate\Support\Facades\DB;

final class ConfigureEventTypeScope
{
    /**
     * @param  array<string, mixed>  $defaultSettings
     * @param  list<EventCapability>  $capabilities
     */
    public function handle(
        string $configurationId,
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
    ): void {
        DB::transaction(function () use (
            $configurationId,
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
        ): void {
            $configuration = EventTypeScope::query()
                ->whereKey($configurationId)
                ->lockForUpdate()
                ->firstOrFail();

            $configuration->forceFill([
                'is_active' => $isActive,
                'default_duration_minutes' => $defaultDurationMinutes,
                'default_capacity' => $defaultCapacity,
                'schedule_source' => $scheduleSource->value,
                'recurrence_policy' => $recurrencePolicy->value,
                'default_recurrence_frequency' => $defaultRecurrenceFrequency->value,
                'default_recurrence_interval' => $defaultRecurrenceInterval,
                'minimum_repeat_interval_minutes' => $minimumRepeatIntervalMinutes,
                'default_registration_opens_minutes_before' => $defaultRegistrationOpensMinutesBefore,
                'default_registration_closes_minutes_before' => $defaultRegistrationClosesMinutesBefore,
                'default_instructions_key' => $defaultInstructionsKey,
                'default_settings' => $defaultSettings,
            ])->save();

            EventTypeCapability::query()
                ->where('event_type_scope_id', $configuration->id)
                ->delete();

            foreach ($capabilities as $capability) {
                EventTypeCapability::query()->create([
                    'event_type_scope_id' => $configuration->id,
                    'capability' => $capability->value,
                ]);
            }
        });
    }
}
