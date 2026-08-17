<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\Events;

use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Events\Services\RecurrenceCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\v3\TestCase;

final class RecurrencePolicyBehaviorV3Test extends TestCase
{
    public function test_recurrence_calculation_preserves_local_start_and_interval(): void
    {
        $first = CarbonImmutable::parse('2026-08-16 18:00:00', 'America/Toronto');
        $occurrences = app(RecurrenceCalculator::class)->calculate(
            $first,
            RecurrenceFrequency::Weekly,
            2,
            $first->addWeeks(4),
        );

        self::assertCount(3, $occurrences);
        self::assertSame($first->toIso8601String(), $occurrences[0]->toIso8601String());
        self::assertSame($first->addWeeks(2)->toIso8601String(), $occurrences[1]->toIso8601String());
        self::assertSame($first->addWeeks(4)->toIso8601String(), $occurrences[2]->toIso8601String());
    }

    public function test_invalid_recurrence_interval_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(RecurrenceCalculator::class)->calculate(
            CarbonImmutable::parse('2026-08-16 18:00:00', 'UTC'),
            RecurrenceFrequency::Daily,
            0,
        );
    }
}
