<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Services;

use App\Contexts\Operations\EventCore\Enums\RecurrenceFrequency;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class RecurrenceCalculator
{
    /** @return list<CarbonImmutable> */
    public function calculate(
        CarbonImmutable $firstLocalStart,
        RecurrenceFrequency $frequency,
        int $interval = 1,
        ?CarbonImmutable $untilLocal = null,
        int $limit = 64,
    ): array {
        if ($interval < 1 || $interval > 52) {
            throw new InvalidArgumentException('Recurrence interval must be between 1 and 52.');
        }

        if ($limit < 1 || $limit > 366) {
            throw new InvalidArgumentException('Occurrence generation limit must be between 1 and 366.');
        }

        if ($untilLocal !== null && $untilLocal->lessThan($firstLocalStart)) {
            throw new InvalidArgumentException('Recurrence end must not be before the first occurrence.');
        }

        $occurrences = [];
        $candidate = $firstLocalStart;

        while (count($occurrences) < $limit) {
            if ($untilLocal !== null && $candidate->greaterThan($untilLocal)) {
                break;
            }

            $occurrences[] = $candidate;

            if ($frequency === RecurrenceFrequency::None) {
                break;
            }

            $candidate = match ($frequency) {
                RecurrenceFrequency::None => $candidate,
                RecurrenceFrequency::Daily => $candidate->addDays($interval),
                RecurrenceFrequency::Weekly => $candidate->addWeeks($interval),
            };
        }

        return $occurrences;
    }
}
