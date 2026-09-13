<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class RecurrenceCalculator
{
    public const DEFAULT_LIMIT = 64;

    /** @return list<CarbonImmutable> */
    public function calculate(
        CarbonImmutable $firstLocalStart,
        RecurrenceFrequency $frequency,
        int $interval = 1,
        ?CarbonImmutable $untilLocal = null,
        int $limit = self::DEFAULT_LIMIT,
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
                RecurrenceFrequency::Daily => $candidate->addDays($interval),
                RecurrenceFrequency::Weekly => $candidate->addWeeks($interval),
            };
        }

        return $occurrences;
    }
}
