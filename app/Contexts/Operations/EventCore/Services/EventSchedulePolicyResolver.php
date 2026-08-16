<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\Operations\EventCore\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\EventCore\Enums\RecurrenceFrequency;
use InvalidArgumentException;

final class EventSchedulePolicyResolver
{
    /**
     * @param  array<string, mixed>  $defaults
     * @return array{frequency: RecurrenceFrequency, interval: int}
     */
    public function resolve(
        array $defaults,
        ?RecurrenceFrequency $requestedFrequency = null,
        ?int $requestedInterval = null,
    ): array {
        $policy = EventRecurrencePolicy::from((string) $defaults['recurrence_policy']);
        $defaultFrequency = RecurrenceFrequency::from((string) $defaults['default_recurrence_frequency']);
        $defaultInterval = (int) $defaults['default_recurrence_interval'];

        if ($policy === EventRecurrencePolicy::Disabled) {
            if ($requestedFrequency !== null && $requestedFrequency !== RecurrenceFrequency::None) {
                throw new InvalidArgumentException('This event type follows the game schedule and cannot recur automatically.');
            }

            return ['frequency' => RecurrenceFrequency::None, 'interval' => 1];
        }

        if ($policy === EventRecurrencePolicy::FixedInterval) {
            if ($requestedFrequency !== null && $requestedFrequency !== $defaultFrequency) {
                throw new InvalidArgumentException('This event type uses a fixed recurrence frequency.');
            }
            if ($requestedInterval !== null && $requestedInterval !== $defaultInterval) {
                throw new InvalidArgumentException('This event type uses a fixed recurrence interval.');
            }

            $this->assertMinimumInterval($defaultFrequency, $defaultInterval, $defaults['minimum_repeat_interval_minutes']);

            return ['frequency' => $defaultFrequency, 'interval' => $defaultInterval];
        }

        $frequency = $requestedFrequency ?? $defaultFrequency;
        $interval = $requestedInterval ?? $defaultInterval;
        if ($interval < 1 || $interval > 52) {
            throw new InvalidArgumentException('Recurrence interval must be between 1 and 52.');
        }

        $this->assertMinimumInterval($frequency, $interval, $defaults['minimum_repeat_interval_minutes']);

        return ['frequency' => $frequency, 'interval' => $frequency === RecurrenceFrequency::None ? 1 : $interval];
    }

    private function assertMinimumInterval(
        RecurrenceFrequency $frequency,
        int $interval,
        mixed $minimumRepeatIntervalMinutes,
    ): void {
        if ($frequency === RecurrenceFrequency::None || $minimumRepeatIntervalMinutes === null) {
            return;
        }

        $actualMinutes = match ($frequency) {
            RecurrenceFrequency::Daily => $interval * 1440,
            RecurrenceFrequency::Weekly => $interval * 10080,
        };

        if ($actualMinutes < (int) $minimumRepeatIntervalMinutes) {
            throw new InvalidArgumentException('Recurrence interval is shorter than the event type minimum.');
        }
    }
}
