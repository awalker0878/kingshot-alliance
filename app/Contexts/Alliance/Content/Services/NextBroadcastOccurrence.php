<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Services;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class NextBroadcastOccurrence
{
    /** @param non-empty-list<int> $weekdays */
    public function calculate(
        array $weekdays,
        string $localTime,
        string $timezone,
        ?CarbonImmutable $after = null,
        ?CarbonImmutable $endsAt = null,
    ): ?CarbonImmutable {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $localTime) !== 1) {
            throw new InvalidArgumentException('Broadcast local time must use HH:MM.');
        }
        if ($weekdays === [] || array_diff($weekdays, range(1, 7)) !== []) {
            throw new InvalidArgumentException('Broadcast weekdays must use ISO day numbers 1–7.');
        }

        $zone = new DateTimeZone($timezone);
        $cursor = ($after ?? CarbonImmutable::now())->setTimezone($zone);
        [$hour, $minute] = array_map('intval', explode(':', $localTime));

        for ($offset = 0; $offset <= 7; $offset++) {
            $candidate = $cursor->startOfDay()->addDays($offset)->setTime($hour, $minute);
            if (! in_array($candidate->dayOfWeekIso, $weekdays, true) || ! $candidate->isAfter($cursor)) {
                continue;
            }

            $utc = $candidate->utc();
            if ($endsAt !== null && $utc->isAfter($endsAt)) {
                return null;
            }

            return $utc;
        }

        return null;
    }
}
