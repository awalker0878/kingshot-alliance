<?php

declare(strict_types=1);

namespace App\Application\Events;

use App\Domain\Events\Enums\RecurrenceFrequency;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class RecurrenceCalculator
{
    /**
     * @return list<CarbonImmutable>
     */
    public function calculate(
        CarbonImmutable $firstLocalStart,
        RecurrenceFrequency $frequency,
        int $interval = 1,
        ?CarbonImmutable $untilLocal = null,
        int $limit = 32,
    ): array {
        if ($interval < 1) {
            throw new InvalidArgumentException('Recurrence interval must be at least one.');
        }

        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Occurrence generation limit must be between 1 and 100.');
        }

        $occurrences = [];
        $candidate = $firstLocalStart;

        while (count($occurrences) < $limit) {
            if ($untilLocal !== null && $candidate->greaterThan($untilLocal)) {
                break;
            }

            $occurrences[] = $candidate;

            $candidate = match ($frequency) {
                RecurrenceFrequency::None => $candidate,
                RecurrenceFrequency::Daily => $candidate->addDays($interval),
                RecurrenceFrequency::Weekly => $candidate->addWeeks($interval),
            };

            if ($frequency === RecurrenceFrequency::None) {
                break;
            }
        }

        return $occurrences;
    }
}
