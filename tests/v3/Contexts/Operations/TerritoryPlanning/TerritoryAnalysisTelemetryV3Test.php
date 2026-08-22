<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\TerritoryPlanning;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutAnalyzer;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\v3\TestCase;

final class TerritoryAnalysisTelemetryV3Test extends TestCase
{
    private const DATASET_ID = 'kingshot-community-observed-2026-08-21-v1';

    public function test_analysis_telemetry_records_scale_and_latency_without_layout_content(): void
    {
        Log::spy();
        $dataset = app(KingdomMapDatasetQuery::class)->require(self::DATASET_ID);

        app(TerritoryLayoutAnalyzer::class)->analyze($dataset, [
            [
                'key' => 'private-city-key',
                'type' => 'governor_city',
                'x' => 321,
                'y' => 654,
                'alliance_key' => 'private-alliance-key',
            ],
            [
                'key' => 'private-trap-key',
                'type' => 'bear_trap',
                'x' => 333,
                'y' => 666,
                'alliance_key' => 'private-alliance-key',
            ],
        ], ['march_seconds_per_tile' => 2]);

        Log::shouldHaveReceived('info')
            ->once()
            ->with(
                'territory.analysis.completed',
                Mockery::on(static function (array $context): bool {
                    self::assertSame(self::DATASET_ID, $context['map_dataset_id'] ?? null);
                    self::assertSame(2, $context['object_count'] ?? null);
                    self::assertSame(1, $context['alliance_count'] ?? null);
                    self::assertIsFloat($context['elapsed_ms'] ?? null);
                    self::assertArrayNotHasKey('objects', $context);
                    self::assertArrayNotHasKey('coordinates', $context);
                    self::assertArrayNotHasKey('governors', $context);
                    self::assertStringNotContainsString('private-', json_encode($context, JSON_THROW_ON_ERROR));
                    self::assertStringNotContainsString('321', json_encode($context, JSON_THROW_ON_ERROR));
                    self::assertStringNotContainsString('654', json_encode($context, JSON_THROW_ON_ERROR));

                    return true;
                }),
            );
    }
}
