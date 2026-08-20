<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\Roster;

use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\ReadModels\Roster\Services\PlayerProgressionTimeline;
use Illuminate\Support\Carbon;
use Tests\v3\TestCase;

final class PlayerProgressionTimelineBehaviorV3Test extends TestCase
{
    public function test_newest_first_observations_report_power_and_identity_changes(): void
    {
        $changes = app(PlayerProgressionTimeline::class)->changes([
            $this->snapshot('01JNEWEST00000000000000000', 'Nova Prime', '150', 'FC2', 'ABC', '2026-08-19T12:00:00Z'),
            $this->snapshot('01JMIDDLE00000000000000000', 'Nova', '100', 'FC1', 'ABC', '2026-08-12T12:00:00Z'),
            $this->snapshot('01JOLDEST00000000000000000', 'Nova', '120', 'FC1', 'XYZ', '2026-08-05T12:00:00Z'),
        ]);

        self::assertSame('50', $changes['01JNEWEST00000000000000000']['power']);
        self::assertSame(
            ['from' => 'Nova', 'to' => 'Nova Prime'],
            $changes['01JNEWEST00000000000000000']['observedName'],
        );
        self::assertSame(
            ['from' => 'FC1', 'to' => 'FC2'],
            $changes['01JNEWEST00000000000000000']['progressionLevel'],
        );
        self::assertNull($changes['01JNEWEST00000000000000000']['observedAllianceTag']);

        self::assertSame('-20', $changes['01JMIDDLE00000000000000000']['power']);
        self::assertSame(
            ['from' => 'XYZ', 'to' => 'ABC'],
            $changes['01JMIDDLE00000000000000000']['observedAllianceTag'],
        );
        self::assertNull($changes['01JOLDEST00000000000000000']);
    }

    private function snapshot(
        string $id,
        string $name,
        string $power,
        ?string $progression,
        ?string $tag,
        string $capturedAt,
    ): PlayerSnapshot {
        $snapshot = new PlayerSnapshot;
        $snapshot->forceFill([
            'id' => $id,
            'observed_name' => $name,
            'power' => $power,
            'progression_level' => $progression,
            'observed_alliance_tag' => $tag,
            'captured_at' => Carbon::parse($capturedAt),
        ]);

        return $snapshot;
    }
}
