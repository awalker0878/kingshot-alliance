<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Application\Events\RecurrenceCalculator;
use App\Domain\Events\Enums\RecurrenceFrequency;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RecurrenceCalculatorTest extends TestCase
{
    public function test_weekly_recurrence_preserves_local_wall_clock_time_across_dst(): void
    {
        $calculator = new RecurrenceCalculator();
        $first = CarbonImmutable::create(2026, 3, 1, 20, 0, 0, 'America/Toronto');

        $occurrences = $calculator->calculate(
            $first,
            RecurrenceFrequency::Weekly,
            interval: 1,
            limit: 3,
        );

        self::assertSame('2026-03-01 20:00 -05:00', $occurrences[0]->format('Y-m-d H:i P'));
        self::assertSame('2026-03-08 20:00 -04:00', $occurrences[1]->format('Y-m-d H:i P'));
        self::assertSame('2026-03-15 20:00 -04:00', $occurrences[2]->format('Y-m-d H:i P'));

        self::assertSame('2026-03-02 01:00', $occurrences[0]->utc()->format('Y-m-d H:i'));
        self::assertSame('2026-03-09 00:00', $occurrences[1]->utc()->format('Y-m-d H:i'));
    }

    public function test_one_time_recurrence_returns_only_the_anchor(): void
    {
        $calculator = new RecurrenceCalculator();
        $first = CarbonImmutable::parse('2026-08-07 12:30', 'Asia/Baghdad');

        $occurrences = $calculator->calculate($first, RecurrenceFrequency::None, limit: 20);

        self::assertCount(1, $occurrences);
        self::assertTrue($first->equalTo($occurrences[0]));
    }

    public function test_recurrence_stops_at_the_configured_local_until_boundary(): void
    {
        $calculator = new RecurrenceCalculator();
        $first = CarbonImmutable::parse('2026-08-01 18:00', 'America/Toronto');
        $until = CarbonImmutable::parse('2026-08-03 18:00', 'America/Toronto');

        $occurrences = $calculator->calculate(
            $first,
            RecurrenceFrequency::Daily,
            interval: 1,
            untilLocal: $until,
            limit: 20,
        );

        self::assertCount(3, $occurrences);
        self::assertSame('2026-08-03 18:00', $occurrences[2]->format('Y-m-d H:i'));
    }

    public function test_invalid_recurrence_interval_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RecurrenceCalculator())->calculate(
            CarbonImmutable::now('UTC'),
            RecurrenceFrequency::Weekly,
            interval: 0,
        );
    }
}
