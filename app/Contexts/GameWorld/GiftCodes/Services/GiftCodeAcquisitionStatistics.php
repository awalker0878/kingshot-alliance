<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeAcquisitionStatistics
{
    /** @param list<int> $values */
    public function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    /** @param list<int> $values */
    public function percentile(array $values, float $percentile): ?int
    {
        if ($values === []) {
            return null;
        }
        sort($values, SORT_NUMERIC);
        $percentile = max(0.0, min(1.0, $percentile));
        $index = (int) ceil($percentile * count($values)) - 1;

        return $values[max(0, min(count($values) - 1, $index))];
    }
}
