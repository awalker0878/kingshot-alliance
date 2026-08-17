<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\KingPerks;

use App\Contexts\Operations\KingPerks\Services\KingPerkPreparationPresetCatalog;
use Carbon\CarbonImmutable;
use Tests\v3\TestCase;

final class KingPerkPolicyBehaviorV3Test extends TestCase
{
    public function test_preparation_presets_cover_each_day_without_exceeding_the_event_window(): void
    {
        $start = CarbonImmutable::parse('2026-08-16 00:00:00', 'UTC');
        $end = $start->addDays(6);
        $days = app(KingPerkPreparationPresetCatalog::class)->forWindow($start, $end);

        self::assertCount(6, $days);
        self::assertSame('construction', $days[0]['focus']);
        self::assertSame('research', $days[1]['focus']);
        self::assertSame('training', $days[3]['focus']);
        self::assertSame('healing', $days[5]['focus']);
        self::assertSame($start->toIso8601String(), $days[0]['startsAt']);
        self::assertSame($end->toIso8601String(), $days[5]['endsAt']);
    }
}
