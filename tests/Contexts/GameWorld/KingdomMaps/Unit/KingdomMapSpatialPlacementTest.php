<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomMaps\Unit;

use App\Contexts\GameWorld\KingdomMaps\Services\KingdomMapSpatialPlacementIndex;
use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use App\Contexts\GameWorld\KingdomMaps\Services\TerritoryCoverageGeometry;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\Rectangle;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Contexts\GameWorld\KingdomMaps\Support\ReadsTerritoryGeometryFixture;
use Tests\Support\RepositoryPath;

final class KingdomMapSpatialPlacementTest extends TestCase
{
    use ReadsTerritoryGeometryFixture;

    public function test_exact_cells_resources_nonzero_origin_edges_and_seeded_queries_match_shared_fixture(): void
    {
        $fixture = $this->spatialFixture();
        $dataset = $this->dataset($fixture);
        $index = new KingdomMapSpatialPlacementIndex;
        foreach ($fixture['queries'] as $case) {
            $rect = $case['footprint'];
            self::assertSame($case['expected_codes'], $index->intersections($dataset, new Rectangle($rect['x'], $rect['y'], $rect['width'], $rect['height'])), $case['name']);
        }
    }

    public function test_server_validation_uses_the_rotated_footprint_and_preserves_both_source_conflicts(): void
    {
        $fixture = $this->spatialFixture();
        $dataset = $this->dataset($fixture);
        $validator = new PlacementValidator(new TerritoryCoverageGeometry, new KingdomMapSpatialPlacementIndex);
        foreach ($fixture['validation_cases'] as $case) {
            $result = $validator->validate($dataset, [$case['object']]);
            self::assertSame($case['expected_codes'], array_column($result->violations, 'code'), $case['name']);
        }
        $outside = ['key' => 'outside', 'type' => 'governor_city', 'x' => 99, 'y' => 199, 'alliance_key' => 'alpha'];
        self::assertSame(['map_bounds'], array_column($validator->validate($dataset, [$outside])->violations, 'code'));
    }

    public function test_presentational_visibility_does_not_change_facts_and_cache_does_not_cross_release_objects(): void
    {
        $fixture = $this->spatialFixture();
        $index = new KingdomMapSpatialPlacementIndex;
        $dataset = $this->dataset($fixture);
        $rect = new Rectangle(110, 210, 1, 1);
        self::assertSame(['terrain_collision', 'resource_collision'], $index->intersections($dataset, $rect));
        $fixture['dataset']['data']['layers'] = ['terrain' => ['visible' => false], 'resources' => ['visible' => false]];
        self::assertSame(['terrain_collision', 'resource_collision'], $index->intersections($this->dataset($fixture), $rect));
        // Same human-readable ID cannot alias a different immutable object in cache.
        $fixture['dataset']['data']['terrain_features'] = [];
        $fixture['dataset']['data']['resource_nodes'] = [];
        self::assertSame([], $index->intersections($this->dataset($fixture), $rect));
        self::assertSame(['terrain_collision', 'resource_collision'], $index->intersections($dataset, $rect));
    }

    public function test_only_sourced_blocking_declarations_enable_placement_constraints(): void
    {
        $fixture = $this->spatialFixture();
        $fixture['dataset']['data']['resource_layers']['terrain']['placement_blocking'] = false;
        self::assertSame(['resource_collision'], (new KingdomMapSpatialPlacementIndex)->intersections($this->dataset($fixture), new Rectangle(110, 210, 1, 1)));
        $fixture['dataset']['data']['resource_layers']['resources']['data_state'] = 'authorized_source_corpus_reference';
        self::assertSame([], (new KingdomMapSpatialPlacementIndex)->intersections($this->dataset($fixture), new Rectangle(110, 210, 1, 1)));
    }

    public function test_claimed_materialization_without_geometry_fails_instead_of_silently_allowing_placement(): void
    {
        $fixture = $this->spatialFixture();
        unset($fixture['dataset']['data']['terrain_features']);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('has not been hydrated');
        (new KingdomMapSpatialPlacementIndex)->intersections($this->dataset($fixture), new Rectangle(110, 210, 1, 1));
    }

    /** @return array<string,mixed> */
    private function spatialFixture(): array
    {
        $raw = file_get_contents(RepositoryPath::fromRoot('tests/Contexts/GameWorld/KingdomMaps/Fixtures/spatial-geometry.json'));
        self::assertIsString($raw);
        $fixture = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);

        return $fixture;
    }
}
