<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomMaps\Unit;

use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use App\Contexts\GameWorld\KingdomMaps\Services\TerritoryCoverageGeometry;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\Rectangle;
use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Contexts\GameWorld\KingdomMaps\Support\ReadsTerritoryGeometryFixture;

final class KingdomMapPlacementParityTest extends TestCase
{
    use ReadsTerritoryGeometryFixture;

    /** @throws JsonException */
    public function test_shared_golden_validation_cases_match_server_geometry(): void
    {
        $fixture = $this->fixture();
        $dataset = $this->dataset($fixture);
        $cases = $fixture['validation_cases'] ?? null;
        if (! is_array($cases)) {
            throw new RuntimeException('Territory geometry fixture must contain validation_cases.');
        }

        foreach ($cases as $case) {
            if (! is_array($case)) {
                throw new RuntimeException('Each Territory geometry validation case must be an object.');
            }

            $objects = $case['objects'] ?? null;
            $preferences = $case['preferences'] ?? null;
            $expectedViolations = $case['expected_violations'] ?? null;
            $expectedWarnings = $case['expected_warnings'] ?? null;
            $expectedSuggestions = $case['expected_suggestions'] ?? null;
            if (
                ! is_array($objects)
                || ! is_array($preferences)
                || ! is_array($expectedViolations)
                || ! is_array($expectedWarnings)
                || ! is_array($expectedSuggestions)
            ) {
                throw new RuntimeException('Territory geometry validation fixture shape is invalid.');
            }

            $result = (new PlacementValidator(new TerritoryCoverageGeometry))->validate($dataset, $objects, $preferences);

            self::assertSame(
                $this->stringList($expectedViolations),
                $this->issueKeys($result->violations),
                (string) ($case['name'] ?? 'unnamed').' violation contract drifted.',
            );
            self::assertSame(
                $this->stringList($expectedWarnings),
                $this->issueKeys($result->warnings),
                (string) ($case['name'] ?? 'unnamed').' warning contract drifted.',
            );
            self::assertSame(
                $this->stringList($expectedSuggestions),
                $this->issueKeys($result->suggestions),
                (string) ($case['name'] ?? 'unnamed').' suggestion contract drifted.',
            );
        }
    }

    public function test_rectangular_rotation_and_nonzero_origins_match_shared_geometry(): void
    {
        $fixture = $this->fixture();
        $geometry = new TerritoryCoverageGeometry;
        foreach ($fixture['geometry_cases'] as $case) {
            $custom = $fixture;
            $custom['dataset']['data']['object_types']['headquarters'] = ['footprint' => $case['footprint'], 'coverage' => $case['coverage']];
            $dataset = $this->dataset($custom);
            $footprint = $geometry->footprint($dataset, 'headquarters', $case['x'], $case['y'], $case['rotation']);
            $coverage = $geometry->coverage($dataset, 'headquarters', $case['x'], $case['y'], $case['rotation']);
            self::assertSame($case['expected_footprint'], (array) $footprint);
            self::assertSame($case['expected_coverage'], (array) $coverage);
        }
        self::assertSame(14, $geometry->unionArea([
            new Rectangle(0, 0, 3, 3),
            new Rectangle(1, 1, 3, 3),
        ]));
    }

    /**
     * @param  list<array{code: string, message: string, object_key?: string}>  $issues
     * @return list<string>
     */
    private function issueKeys(array $issues): array
    {
        $keys = array_map(
            static fn (array $issue): string => $issue['code'].':'.($issue['object_key'] ?? ''),
            $issues,
        );
        sort($keys);

        return array_values($keys);
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        $strings = array_map(static fn (mixed $value): string => (string) $value, $values);
        sort($strings);

        return array_values($strings);
    }
}
