<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Unit;

use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use App\Contexts\GameWorld\KingdomMaps\Services\TerritoryCoverageGeometry;
use App\Contexts\Operations\TerritoryPlanning\Services\HiveLayoutGenerator;
use PHPUnit\Framework\TestCase;
use Tests\Contexts\GameWorld\KingdomMaps\Support\ReadsTerritoryGeometryFixture;

final class TerritoryHiveGenerationTest extends TestCase
{
    use ReadsTerritoryGeometryFixture;

    public function test_both_styles_return_exact_valid_city_count_and_deterministic_proposals(): void
    {
        $geometry = new TerritoryCoverageGeometry;
        $validator = new PlacementValidator($geometry);
        $generator = new HiveLayoutGenerator($validator, $geometry);
        $dataset = $this->dataset($this->fixture());
        $existing = [['key' => 'hq', 'type' => 'headquarters', 'x' => 20, 'y' => 20, 'alliance_key' => 'alpha']];
        foreach (['swirl', 'banner_pad'] as $style) {
            $result = $generator->preview($dataset, $existing, $style, 'alpha', 25, 25, 8);
            self::assertSame('feasible', $result['status'], json_encode($result['diagnostics'], JSON_THROW_ON_ERROR));
            self::assertSame(8, $result['generated_city_count']);
            self::assertCount(8, array_filter($result['objects'], static fn (array $object): bool => $object['type'] === 'governor_city'));
            self::assertTrue($validator->validate($dataset, array_merge($existing, $result['objects']))->valid());
            self::assertSame($result, $generator->preview($dataset, $existing, $style, 'alpha', 25, 25, 8));
        }
        self::assertCount(1, $existing);
    }

    public function test_infeasible_proposal_exposes_diagnostics_without_partial_additions(): void
    {
        $geometry = new TerritoryCoverageGeometry;
        $generator = new HiveLayoutGenerator(new PlacementValidator($geometry), $geometry);
        $dataset = $this->dataset($this->fixture());
        $missing = $generator->preview($dataset, [], 'swirl', 'alpha', 20, 20, 8);
        self::assertSame('headquarters_required', $missing['diagnostics'][0]['code']);
        $hq = [['key' => 'hq', 'type' => 'headquarters', 'x' => 20, 'y' => 20, 'alliance_key' => 'alpha']];
        $blocked = $generator->preview($dataset, $hq, 'swirl', 'alpha', 40, 40, 8);
        self::assertSame('bear_location_blocked', $blocked['diagnostics'][0]['code']);
        self::assertSame([], $blocked['objects']);
        $distant = $generator->preview($dataset, $hq, 'swirl', 'alpha', 90, 90, 100, 10);
        self::assertSame('infeasible', $distant['status']);
        self::assertSame([], $distant['objects']);
        self::assertSame('bounded_search_shortfall', $distant['diagnostics'][0]['code']);
    }
}
