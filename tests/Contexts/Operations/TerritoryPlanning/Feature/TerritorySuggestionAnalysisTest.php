<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Feature;

use App\Contexts\Operations\TerritoryPlanning\Services\TerritorySuggestionGenerator;
use Tests\Contexts\GameWorld\KingdomMaps\Support\ReadsTerritoryGeometryFixture;
use Tests\TestCase;

final class TerritorySuggestionAnalysisTest extends TestCase
{
    use ReadsTerritoryGeometryFixture;

    public function test_officer_preset_exposes_explicit_components_weights_and_reproducible_pins(): void
    {
        $dataset = $this->dataset($this->fixture());
        $existing = [['key' => 'hq', 'type' => 'headquarters', 'x' => 20, 'y' => 20, 'alliance_key' => 'alpha', 'rotation' => 0]];
        $preferences = ['preferred_bear_radius_tiles' => 50];

        $result = app(TerritorySuggestionGenerator::class)->compare(
            $dataset, $existing, 'alpha', 25, 25, 8, 1, $preferences,
        );

        self::assertSame('hive-grid-v2', $result['algorithm_version']);
        self::assertCount(2, $result['candidates']);
        foreach ($result['candidates'] as $candidate) {
            self::assertSame('feasible', $candidate['status']);
            self::assertSame($dataset->id, $candidate['map_dataset_id']);
            self::assertSame($dataset->checksum, $candidate['map_dataset_checksum']);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $candidate['input_checksum']);
            self::assertSame('officer-planning-v1', $candidate['score']['preset']);
            self::assertSame([
                'coverage' => 0.4,
                'banner_efficiency' => 0.25,
                'distance' => 0.2,
                'density' => 0.15,
            ], $candidate['score']['weights']);
            self::assertSame('available', $candidate['score']['state']);
            self::assertIsFloat($candidate['score']['value']);
            self::assertSame([], $candidate['score']['missing_components']);
        }

        self::assertSame(
            $result,
            app(TerritorySuggestionGenerator::class)->compare(
                $dataset, $existing, 'alpha', 25, 25, 8, 1, $preferences,
            ),
        );
    }

    public function test_missing_component_never_silently_reweights_the_officer_score(): void
    {
        $dataset = $this->dataset($this->fixture());
        $existing = [['key' => 'hq', 'type' => 'headquarters', 'x' => 20, 'y' => 20, 'alliance_key' => 'alpha', 'rotation' => 0]];

        $result = app(TerritorySuggestionGenerator::class)->compare(
            $dataset, $existing, 'alpha', 25, 25, 8,
        );

        foreach ($result['candidates'] as $candidate) {
            self::assertSame('feasible', $candidate['status']);
            self::assertSame('unavailable', $candidate['score']['state']);
            self::assertNull($candidate['score']['value']);
            self::assertContains('distance', $candidate['score']['missing_components']);
            self::assertNull($candidate['score']['components']['distance']);
            self::assertSame(0.2, $candidate['score']['weights']['distance']);
        }
    }
}
