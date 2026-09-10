<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomMaps\Feature;

use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutAnalyzer;
use JsonException;
use RuntimeException;
use Tests\Contexts\GameWorld\KingdomMaps\Support\ReadsTerritoryGeometryFixture;
use Tests\TestCase;

final class KingdomMapGeometryParityV3Test extends TestCase
{
    use ReadsTerritoryGeometryFixture;

    /** @throws JsonException */
    public function test_shared_golden_analysis_case_matches_server_analysis(): void
    {
        $fixture = $this->fixture();
        $dataset = $this->dataset($fixture);
        $analysisCase = $fixture['analysis_case'] ?? null;
        if (! is_array($analysisCase)) {
            throw new RuntimeException('Territory geometry fixture must contain analysis_case.');
        }

        $objects = $analysisCase['objects'] ?? null;
        $preferences = $analysisCase['preferences'] ?? null;
        $expected = $analysisCase['expected'] ?? null;
        if (! is_array($objects) || ! is_array($preferences) || ! is_array($expected)) {
            throw new RuntimeException('Territory analysis fixture shape is invalid.');
        }

        $analysis = app(TerritoryLayoutAnalyzer::class)->analyze($dataset, $objects, $preferences);

        self::assertSame($expected, $analysis['alliances']['alpha'] ?? null);
    }
}
