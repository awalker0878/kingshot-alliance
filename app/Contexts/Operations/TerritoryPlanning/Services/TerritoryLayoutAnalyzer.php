<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use App\Contexts\GameWorld\KingdomMaps\Services\TerritoryCoverageGeometry;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\Rectangle;

final readonly class TerritoryLayoutAnalyzer
{
    public function __construct(
        private PlacementValidator $placement,
        private TerritoryCoverageAnalyzer $coverage,
        private TerritoryCoverageGeometry $coverageGeometry,
        private TerritoryPlanningTelemetry $telemetry,
    ) {}

    /**
     * @param  list<array{key: string, type: string, x: int, y: int, alliance_key: string}>  $objects
     * @param  array<string, mixed>  $preferences
     * @return array<string, mixed>
     */
    public function analyze(
        KingdomMapDataset $dataset,
        array $objects,
        array $preferences = [],
    ): array {
        $startedAt = hrtime(true);
        $byAlliance = [];
        $allianceByObjectKey = [];
        foreach ($objects as $object) {
            $byAlliance[$object['alliance_key']][] = $object;
            $allianceByObjectKey[$object['key']] = $object['alliance_key'];
        }

        $validation = $this->placement->validate($dataset, $objects, $preferences);
        $issueCounts = [];
        $this->countIssues($validation->violations, 'violation_count', $allianceByObjectKey, $issueCounts);
        $this->countIssues($validation->warnings, 'warning_count', $allianceByObjectKey, $issueCounts);
        $this->countIssues($validation->suggestions, 'suggestion_count', $allianceByObjectKey, $issueCounts);
        $coverageByCity = $this->coverage->byGovernorCity($dataset, $objects);

        $selectedBearTraps = is_array($preferences['selected_bear_trap_by_alliance'] ?? null)
            ? $preferences['selected_bear_trap_by_alliance']
            : [];
        $result = [];
        foreach ($byAlliance as $allianceKey => $allianceObjects) {
            $coverageRectangles = [];
            $cities = [];
            $traps = [];
            $counts = [];

            foreach ($allianceObjects as $object) {
                $counts[$object['type']] = ($counts[$object['type']] ?? 0) + 1;
                $coverageRectangle = $this->coverageGeometry->coverage($dataset, $object['type'], $object['x'], $object['y']);
                if ($coverageRectangle instanceof Rectangle) {
                    $coverageRectangles[] = $coverageRectangle;
                }
                if ($object['type'] === 'governor_city') {
                    $cities[] = $object;
                }
                if ($object['type'] === 'bear_trap') {
                    $traps[] = $object;
                }
            }

            $covered = 0;
            foreach ($cities as $city) {
                if (($coverageByCity[$city['key']] ?? false) === true) {
                    $covered++;
                }
            }

            $components = $this->coverageGeometry->componentCount($coverageRectangles);
            $marchSecondsPerTile = isset($preferences['march_seconds_per_tile'])
                ? (float) $preferences['march_seconds_per_tile']
                : null;
            $selectedTrapKey = is_string($selectedBearTraps[$allianceKey] ?? null)
                ? $selectedBearTraps[$allianceKey]
                : null;
            $marches = $this->marches($cities, $traps, $marchSecondsPerTile, $selectedTrapKey);
            $distances = array_column($marches, 'distance_tiles');
            $estimatedSeconds = array_values(array_filter(
                array_column($marches, 'estimated_seconds'),
                static fn (?float $seconds): bool => $seconds !== null,
            ));
            $bannerCount = (int) ($counts['banner'] ?? 0);
            $quality = $issueCounts[$allianceKey] ?? [];

            $result[$allianceKey] = [
                'counts' => $counts,
                'governor_cities' => count($cities),
                'covered_governor_cities' => $covered,
                'uncovered_governor_cities' => count($cities) - $covered,
                'coverage_percent' => count($cities) === 0
                    ? null
                    : round(($covered / count($cities)) * 100, 2),
                'territory_components' => $components,
                'territory_connected' => $components <= 1,
                'banner_efficiency' => $bannerCount === 0
                    ? null
                    : round($covered / $bannerCount, 2),
                'violation_count' => (int) ($quality['violation_count'] ?? 0),
                'warning_count' => (int) ($quality['warning_count'] ?? 0),
                'suggestion_count' => (int) ($quality['suggestion_count'] ?? 0),
                'bear_distance_tiles' => $this->statistics($distances),
                'estimated_march_seconds' => $marchSecondsPerTile === null
                    ? null
                    : $this->statistics($estimatedSeconds),
                'march_assumption_seconds_per_tile' => $marchSecondsPerTile,
                'selected_bear_trap_key' => $selectedTrapKey,
                'marches' => $marches,
            ];
        }

        $this->telemetry->analysisCompleted(
            mapDatasetId: $dataset->id,
            objectCount: count($objects),
            allianceCount: count($result),
            elapsedMilliseconds: (hrtime(true) - $startedAt) / 1_000_000,
        );

        return ['alliances' => $result];
    }

    /**
     * @param  list<array{code: string, message: string, object_key?: string}>  $issues
     * @param  array<string, string>  $allianceByObjectKey
     * @param  array<string, array<string, int>>  $counts
     */
    private function countIssues(array $issues, string $metric, array $allianceByObjectKey, array &$counts): void
    {
        foreach ($issues as $issue) {
            $objectKey = $issue['object_key'] ?? null;
            if (! is_string($objectKey)) {
                continue;
            }
            $allianceKey = $allianceByObjectKey[$objectKey] ?? null;
            if (! is_string($allianceKey)) {
                continue;
            }
            $counts[$allianceKey][$metric] = ($counts[$allianceKey][$metric] ?? 0) + 1;
        }
    }

    /**
     * @param  list<array{key: string, type: string, x: int, y: int, alliance_key: string}>  $cities
     * @param  list<array{key: string, type: string, x: int, y: int, alliance_key: string}>  $traps
     * @return list<array{city_key: string, trap_key: string, distance_tiles: float, estimated_seconds: ?float}>
     */
    private function marches(array $cities, array $traps, ?float $secondsPerTile, ?string $selectedTrapKey): array
    {
        if ($traps === []) {
            return [];
        }
        $selectedTrap = null;
        if ($selectedTrapKey !== null) {
            foreach ($traps as $trap) {
                if ($trap['key'] === $selectedTrapKey) {
                    $selectedTrap = $trap;
                    break;
                }
            }
        }
        $marches = [];
        foreach ($cities as $city) {
            $targetTrap = $selectedTrap ?? $traps[0];
            $targetDistance = hypot($city['x'] - $targetTrap['x'], $city['y'] - $targetTrap['y']);
            if ($selectedTrap === null) {
                foreach (array_slice($traps, 1) as $trap) {
                    $distance = hypot($city['x'] - $trap['x'], $city['y'] - $trap['y']);
                    if ($distance < $targetDistance) {
                        $targetDistance = $distance;
                        $targetTrap = $trap;
                    }
                }
            }
            $marches[] = [
                'city_key' => $city['key'],
                'trap_key' => $targetTrap['key'],
                'distance_tiles' => round($targetDistance, 2),
                'estimated_seconds' => $secondsPerTile === null
                    ? null
                    : round($targetDistance * $secondsPerTile, 2),
            ];
        }

        return $marches;
    }

    /**
     * @param  list<float>  $values
     * @return array{average: ?float, median: ?float, max: ?float}
     */
    private function statistics(array $values): array
    {
        if ($values === []) {
            return ['average' => null, 'median' => null, 'max' => null];
        }
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 0 ? ($values[$middle - 1] + $values[$middle]) / 2 : $values[$middle];

        return [
            'average' => round(array_sum($values) / $count, 2),
            'median' => round($median, 2),
            'max' => round(max($values), 2),
        ];
    }
}
