<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\KingdomMaps;

use App\Contexts\GameWorld\KingdomMaps\Enums\MapDatasetConfidence;
use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutAnalyzer;
use JsonException;
use RuntimeException;
use Tests\v3\TestCase;

final class KingdomMapGeometryParityV3Test extends TestCase
{
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

            $result = app(PlacementValidator::class)->validate($dataset, $objects, $preferences);

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

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function fixture(): array
    {
        $contents = file_get_contents(base_path('tests/v3/Fixtures/territory-geometry.json'));
        if (! is_string($contents)) {
            throw new RuntimeException('Unable to read Territory geometry fixture.');
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Territory geometry fixture must decode to an object.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $fixture */
    private function dataset(array $fixture): KingdomMapDataset
    {
        $dataset = $fixture['dataset'] ?? null;
        if (! is_array($dataset) || ! is_array($dataset['data'] ?? null)) {
            throw new RuntimeException('Territory geometry dataset fixture shape is invalid.');
        }

        return new KingdomMapDataset(
            id: (string) ($dataset['id'] ?? ''),
            schemaVersion: (int) ($dataset['schema_version'] ?? 0),
            observedAt: (string) ($dataset['observed_at'] ?? ''),
            sourceLabel: (string) ($dataset['source_label'] ?? ''),
            sourceUri: is_string($dataset['source_uri'] ?? null) ? $dataset['source_uri'] : null,
            confidence: MapDatasetConfidence::from((string) ($dataset['confidence'] ?? '')),
            checksum: (string) ($dataset['checksum'] ?? ''),
            data: $dataset['data'],
        );
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
