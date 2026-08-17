<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Actions;

use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\Events\Enums\EventScheduleSource;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class PersistEventTypeScopeConfiguration
{
    /**
     * @param list<EventCapability> $capabilities
     * @param array<string, mixed> $defaultSettings
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
            $locked = EventTypeScope::query()->whereKey($configurationId)->lockForUpdate()->firstOrFail();
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
                'default_instructions_key' => $defaultInstructionsKey === null || trim($defaultInstructionsKey) === '' ? null : trim($defaultInstructionsKey),
                'default_settings' => $defaultSettings === [] ? null : $defaultSettings,
            ])->save();

            $wanted = array_values(array_unique(array_map(
                static fn (EventCapability $capability): string => $capability->value,
                $capabilities,
            )));
            $locked->capabilities()->whereNotIn('capability', $wanted)->delete();
            foreach ($wanted as $capability) {
                $locked->capabilities()->firstOrCreate(['capability' => $capability], ['configuration' => null]);
            }
        });
    }
}
