<?php

declare(strict_types=1);

namespace Tests\Unit\Contributions;

use App\Domain\Contributions\Enums\ContributionDataClass;
use App\Domain\Contributions\Enums\ContributionPeriod;
use App\Domain\Contributions\Models\ContributionCategory;
use App\Domain\Contributions\Services\ContributionPeriodResolver;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class ContributionPeriodResolverTest extends TestCase
{
    public function test_weekly_period_uses_alliance_timezone_boundaries(): void
    {
        $category = new ContributionCategory([
            'period' => ContributionPeriod::Weekly,
            'data_class' => ContributionDataClass::RecordedFact,
        ]);
        $at = CarbonImmutable::parse('2026-08-07 01:30:00', 'UTC');

        $period = (new ContributionPeriodResolver())->current($category, 'America/Toronto', $at);

        self::assertSame('2026-08-03', $period['start']->toDateString());
        self::assertSame('2026-08-09', $period['end']->toDateString());
        self::assertSame('America/Toronto', $period['start']->getTimezone()->getName());
    }

    public function test_custom_period_preserves_explicit_effective_dates(): void
    {
        $category = new ContributionCategory([
            'period' => ContributionPeriod::Custom,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'data_class' => ContributionDataClass::RecordedFact,
        ]);

        $period = (new ContributionPeriodResolver())->current($category, 'UTC');

        self::assertSame('2026-08-01', $period['start']->toDateString());
        self::assertSame('2026-08-31', $period['end']->toDateString());
    }
}
