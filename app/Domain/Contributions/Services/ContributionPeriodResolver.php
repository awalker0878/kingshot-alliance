<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Services;

use App\Domain\Contributions\Enums\ContributionPeriod;
use App\Domain\Contributions\Models\ContributionCategory;
use Carbon\CarbonImmutable;
use LogicException;

final class ContributionPeriodResolver
{
    /** @return array{start: CarbonImmutable, end: CarbonImmutable} */
    public function current(
        ContributionCategory $category,
        string $timezone,
        ?CarbonImmutable $at = null,
    ): array {
        $at ??= CarbonImmutable::now($timezone);
        $local = $at->setTimezone($timezone);

        return match ($category->period) {
            ContributionPeriod::Daily => [
                'start' => $local->startOfDay(),
                'end' => $local->endOfDay(),
            ],
            ContributionPeriod::Weekly => [
                'start' => $local->startOfWeek(),
                'end' => $local->endOfWeek(),
            ],
            ContributionPeriod::Monthly => [
                'start' => $local->startOfMonth(),
                'end' => $local->endOfMonth(),
            ],
            ContributionPeriod::Season,
            ContributionPeriod::Custom => $this->configured($category, $timezone),
        };
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable} */
    private function configured(ContributionCategory $category, string $timezone): array
    {
        if ($category->period_start === null || $category->period_end === null) {
            throw new LogicException('Season and custom contribution periods require explicit start and end dates.');
        }

        $start = CarbonImmutable::parse($category->period_start->toDateString(), $timezone)->startOfDay();
        $end = CarbonImmutable::parse($category->period_end->toDateString(), $timezone)->endOfDay();

        if ($end->lessThan($start)) {
            throw new LogicException('Contribution period end must not be before the start.');
        }

        return ['start' => $start, 'end' => $end];
    }
}
