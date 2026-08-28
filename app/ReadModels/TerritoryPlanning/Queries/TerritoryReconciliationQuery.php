<?php

declare(strict_types=1);

namespace App\ReadModels\TerritoryPlanning\Queries;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\Intelligence\Observations\Queries\SpatialObservationQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanRevisionQuery;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCoverageAnalyzer;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class TerritoryReconciliationQuery
{
    public function __construct(
        private TerritoryPlanQuery $plans,
        private TerritoryPlanRevisionQuery $revisions,
        private SpatialObservationQuery $observations,
        private KingdomMapDatasetQuery $datasets,
        private TerritoryCoverageAnalyzer $coverage,
    ) {}

    /** @return array<string, mixed> */
    public function build(
        string $actorPlayerId,
        string $planId,
        ?string $revisionId = null,
        ?string $allianceId = null,
        ?string $observationId = null,
    ): array {
        $detail = $this->plans->detail($actorPlayerId, $planId);
        $plan = is_array($detail['plan'] ?? null) ? $detail['plan'] : [];
        $revisionOptions = $this->typedRows($detail['revisions'] ?? null);
        usort(
            $revisionOptions,
            static fn (array $a, array $b): int => ((int) ($b['revision_number'] ?? 0))
                <=> ((int) ($a['revision_number'] ?? 0)),
        );

        if ($revisionOptions === []) {
            return ['state' => 'no_published_revision', 'plan' => $plan, 'revisions' => []];
        }

        $revisionId ??= is_string($revisionOptions[0]['id'] ?? null)
            ? $revisionOptions[0]['id']
            : null;
        if ($revisionId === null) {
            return [
                'state' => 'no_published_revision',
                'plan' => $plan,
                'revisions' => $revisionOptions,
            ];
        }

        $revision = $this->revisions->snapshot($actorPlayerId, $planId, $revisionId);
        $snapshot = is_array($revision['snapshot'] ?? null) ? $revision['snapshot'] : [];
        $snapshotPlan = is_array($snapshot['plan'] ?? null) ? $snapshot['plan'] : [];
        $allianceOptions = $this->allianceOptions($snapshot['alliances'] ?? null);

        if (($snapshotPlan['scope'] ?? null) === 'alliance') {
            $allianceId = is_string($snapshotPlan['owner_alliance_id'] ?? null)
                ? $snapshotPlan['owner_alliance_id']
                : $allianceId;
        } elseif ($allianceId === null && count($allianceOptions) === 1) {
            $allianceId = $allianceOptions[0]['id'];
        }

        if ($allianceId === null || ! in_array($allianceId, array_column($allianceOptions, 'id'), true)) {
            return [
                'state' => 'alliance_required',
                'plan' => $plan,
                'revision' => $revision,
                'revisions' => $revisionOptions,
                'alliance_options' => $allianceOptions,
            ];
        }

        $layer = $this->findAlliance($allianceOptions, $allianceId);
        if ($layer === null) {
            return [
                'state' => 'alliance_required',
                'plan' => $plan,
                'revision' => $revision,
                'revisions' => $revisionOptions,
                'alliance_options' => $allianceOptions,
            ];
        }

        $kingdomId = (string) ($snapshotPlan['kingdom_id'] ?? $plan['kingdom_id'] ?? '');
        $history = $this->observations->history($actorPlayerId, $allianceId, $kingdomId, 50);
        $observation = $observationId === null
            ? $this->observations->latest($actorPlayerId, $allianceId, $kingdomId)
            : $this->observations->detail(
                $actorPlayerId,
                $allianceId,
                $kingdomId,
                $observationId,
            );

        if ($observation === null) {
            return [
                'state' => 'no_observation',
                'plan' => $plan,
                'revision' => $revision,
                'revisions' => $revisionOptions,
                'alliance' => $layer,
                'alliance_options' => $allianceOptions,
                'observations' => $history,
            ];
        }

        $planDataset = $this->datasets->require(
            (string) $revision['map_dataset_id'],
            (string) $revision['map_dataset_checksum'],
        );
        $mapGeometry = $this->mapGeometry($planDataset->data);
        $observedDataset = $this->datasets->require(
            (string) $observation['map_dataset_id'],
            (string) $observation['map_dataset_checksum'],
        );
        $compatibility = $this->compatibility(
            $planDataset->data,
            $observedDataset->data,
            $planDataset->id,
            $observedDataset->id,
            $planDataset->checksum,
            $observedDataset->checksum,
        );
        $freshness = $this->freshness((string) $observation['captured_at']);

        if ($compatibility === 'dataset_incompatible') {
            return [
                'state' => 'dataset_incompatible',
                'plan' => $plan,
                'revision' => $revision,
                'revisions' => $revisionOptions,
                'alliance' => $layer,
                'alliance_options' => $allianceOptions,
                'observation' => $observation,
                'observations' => $history,
                'freshness' => $freshness,
                'compatibility' => $compatibility,
                'map_geometry' => $mapGeometry,
            ];
        }

        $plannedObjects = [];
        foreach ($this->typedRows($snapshot['objects'] ?? null) as $object) {
            if (($object['alliance_key'] ?? null) === $layer['key']) {
                $plannedObjects[] = $object;
            }
        }
        $observedObjects = $this->typedRows($observation['objects'] ?? null);
        $plannedCoverage = $this->coverage->byGovernorCity(
            $planDataset,
            $this->coverageObjects($plannedObjects, $layer['key']),
        );
        $observedCoverage = $this->coverage->byGovernorCity(
            $observedDataset,
            $this->coverageObjects($observedObjects, $layer['key']),
        );
        $observedCoverage = $this->trustedObservedCoverage($observation, $observedCoverage);

        [$governors, $usedObservedGovernorKeys] = $this->governors(
            $plannedObjects,
            $observedObjects,
            $observation,
            $planDataset->data,
            $plannedCoverage,
            $observedCoverage,
        );
        [$structures, $usedObservedStructureKeys] = $this->structures(
            $plannedObjects,
            $observedObjects,
            $observation,
            $planDataset->data,
        );

        $unexpected = [];
        foreach ($observedObjects as $object) {
            $key = (string) ($object['key'] ?? '');
            if (in_array($key, $usedObservedGovernorKeys, true)
                || in_array($key, $usedObservedStructureKeys, true)) {
                continue;
            }

            $type = (string) ($object['type'] ?? '');
            $status = $type === 'governor_city'
                ? match ((string) ($object['identity_state'] ?? 'unresolved')) {
                    'ambiguous' => 'identity_ambiguous',
                    'unresolved' => 'identity_unresolved',
                    default => 'unexpected',
                }
            : 'unexpected';
            $unexpected[] = $object + [
                'status' => $status,
                'observed_covered' => $type === 'governor_city'
                    ? ($observedCoverage[$key] ?? null)
                    : null,
            ];
        }

        return [
            'state' => 'ready',
            'plan' => $plan,
            'revision' => $revision,
            'revisions' => $revisionOptions,
            'alliance' => $layer,
            'alliance_options' => $allianceOptions,
            'observation' => $observation,
            'observations' => $history,
            'freshness' => $freshness,
            'compatibility' => $compatibility,
            'map_geometry' => $mapGeometry,
            'summary' => $this->summary($governors, $structures, $unexpected),
            'governors' => $governors,
            'structures' => $structures,
            'unexpected' => $unexpected,
            'planned_objects' => $plannedObjects,
            'observed_objects' => $observedObjects,
            'policy' => [
                'position_tolerance_tiles' => (float) config(
                    'territory_reconciliation.position_tolerance_tiles',
                    1.0,
                ),
                'banner_match_max_tiles' => (float) config(
                    'territory_reconciliation.banner_match_max_tiles',
                    25.0,
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return array{bounds:array{x:float,y:float,width:float,height:float},coordinate_system:array{name:string,origin:string,tile_size:float},render:array{y_axis:'up'|'down'}}
     */
    private function mapGeometry(array $dataset): array
    {
        $bounds = $dataset['bounds'] ?? null;
        $coordinateSystem = $dataset['coordinate_system'] ?? null;
        if (! is_array($bounds) || ! is_array($coordinateSystem)) {
            throw new RuntimeException('The pinned Kingdom map dataset is missing renderable geometry metadata.');
        }

        foreach (['x', 'y', 'width', 'height'] as $field) {
            if (! is_int($bounds[$field] ?? null) && ! is_float($bounds[$field] ?? null)) {
                throw new RuntimeException('The pinned Kingdom map dataset has invalid map bounds.');
            }
        }
        if ((float) $bounds['width'] <= 0 || (float) $bounds['height'] <= 0) {
            throw new RuntimeException('The pinned Kingdom map dataset has non-positive map bounds.');
        }

        $name = $coordinateSystem['name'] ?? null;
        $origin = $coordinateSystem['origin'] ?? null;
        $tileSize = $coordinateSystem['tile_size'] ?? null;
        if (! is_string($name)
            || ! is_string($origin)
            || (! is_int($tileSize) && ! is_float($tileSize))
            || (float) $tileSize <= 0) {
            throw new RuntimeException('The pinned Kingdom map dataset has invalid coordinate-system metadata.');
        }

        $yAxis = match ($origin) {
            'south_west_game_coordinates' => 'up',
            default => throw new RuntimeException('The pinned Kingdom map coordinate origin is not supported for reconciliation rendering.'),
        };

        return [
            'bounds' => [
                'x' => (float) $bounds['x'],
                'y' => (float) $bounds['y'],
                'width' => (float) $bounds['width'],
                'height' => (float) $bounds['height'],
            ],
            'coordinate_system' => [
                'name' => $name,
                'origin' => $origin,
                'tile_size' => (float) $tileSize,
            ],
            'render' => ['y_axis' => $yAxis],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function typedRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return list<array{id:string,key:string,name:string}> */
    private function allianceOptions(mixed $value): array
    {
        $options = [];
        foreach ($this->typedRows($value) as $layer) {
            if (! is_string($layer['alliance_id'] ?? null)) {
                continue;
            }

            $key = (string) ($layer['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $options[] = [
                'id' => $layer['alliance_id'],
                'key' => $key,
                'name' => (string) ($layer['display_name'] ?? $layer['alliance_id']),
            ];
        }

        return $options;
    }

    /**
     * @param  list<array{id:string,key:string,name:string}>  $options
     * @return array{id:string,key:string,name:string}|null
     */
    private function findAlliance(array $options, string $allianceId): ?array
    {
        foreach ($options as $option) {
            if ($option['id'] === $allianceId) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $planDataset
     * @param  array<string, mixed>  $observedDataset
     */
    private function compatibility(
        array $planDataset,
        array $observedDataset,
        string $planId,
        string $observedId,
        string $planChecksum,
        string $observedChecksum,
    ): string {
        if ($planId === $observedId && hash_equals($planChecksum, $observedChecksum)) {
            return 'same_dataset';
        }

        $planCoordinate = $planDataset['coordinate_system'] ?? null;
        $observedCoordinate = $observedDataset['coordinate_system'] ?? null;
        $compatible = is_array($planCoordinate)
            && is_array($observedCoordinate)
            && $planCoordinate === $observedCoordinate;

        return $compatible ? 'compatible_release' : 'dataset_incompatible';
    }

    /** @return array{age_seconds:int,state:string,fresh_seconds:int,aging_seconds:int} */
    private function freshness(string $capturedAt): array
    {
        $captured = CarbonImmutable::parse($capturedAt)->utc();
        $age = max(0, (int) $captured->diffInSeconds(CarbonImmutable::now('UTC')));
        $fresh = max(1, (int) config('territory_reconciliation.fresh_seconds', 3600));
        $aging = max($fresh, (int) config('territory_reconciliation.aging_seconds', 21600));

        return [
            'age_seconds' => $age,
            'state' => $age <= $fresh ? 'fresh' : ($age <= $aging ? 'aging' : 'stale'),
            'fresh_seconds' => $fresh,
            'aging_seconds' => $aging,
        ];
    }

    /**
     * A captured coverage source can prove positive coverage even in a partial
     * observation. Negative coverage requires a complete-hive observation;
     * otherwise an unseen off-screen Banner could still cover the city.
     *
     * @param  array<string, mixed>  $observation
     * @param  array<string, bool>  $coverage
     * @return array<string, bool|null>
     */
    private function trustedObservedCoverage(array $observation, array $coverage): array
    {
        $completeHive = ($observation['completeness'] ?? null) === 'complete'
            && ($observation['coverage_kind'] ?? null) === 'complete_hive';
        $trusted = [];
        foreach ($coverage as $key => $covered) {
            $trusted[$key] = $covered === true || $completeHive ? $covered : null;
        }

        return $trusted;
    }

    /**
     * @param  list<array<string, mixed>>  $objects
     * @return list<array{key:string,type:string,x:int,y:int,alliance_key:string}>
     */
    private function coverageObjects(array $objects, string $allianceKey): array
    {
        $result = [];
        foreach ($objects as $object) {
            if (! is_string($object['key'] ?? null)
                || ! is_string($object['type'] ?? null)
                || ! is_int($object['x'] ?? null)
                || ! is_int($object['y'] ?? null)) {
                continue;
            }

            $result[] = [
                'key' => $object['key'],
                'type' => $object['type'],
                'x' => $object['x'],
                'y' => $object['y'],
                'alliance_key' => $allianceKey,
            ];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $planned
     * @param  list<array<string, mixed>>  $observed
     * @param  array<string, mixed>  $observation
     * @param  array<string, mixed>  $dataset
     * @param  array<string, bool>  $plannedCoverage
     * @param  array<string, bool|null>  $observedCoverage
     * @return array{0:list<array<string,mixed>>,1:list<string>}
     */
    private function governors(
        array $planned,
        array $observed,
        array $observation,
        array $dataset,
        array $plannedCoverage,
        array $observedCoverage,
    ): array {
        $plannedCities = $this->objectsOfType($planned, 'governor_city');
        $observedCities = $this->objectsOfType($observed, 'governor_city');
        $used = [];
        $rows = [];
        $tolerance = max(
            0.0,
            (float) config('territory_reconciliation.position_tolerance_tiles', 1.0),
        );

        foreach ($plannedCities as $plannedCity) {
            $match = null;
            foreach ($observedCities as $observedCity) {
                if (in_array((string) ($observedCity['key'] ?? ''), $used, true)) {
                    continue;
                }

                if (is_string($plannedCity['player_id'] ?? null)
                    && ($observedCity['identity_state'] ?? null) === 'resolved_player'
                    && ($observedCity['player_id'] ?? null) === $plannedCity['player_id']) {
                    $match = $observedCity;
                    break;
                }

                $external = is_string($plannedCity['external_player_name'] ?? null)
                    ? trim(mb_strtolower($plannedCity['external_player_name']))
                    : '';
                $planLocal = is_string($observedCity['plan_local_identity'] ?? null)
                    ? trim(mb_strtolower($observedCity['plan_local_identity']))
                    : '';
                if ($external !== ''
                    && ($observedCity['identity_state'] ?? null) === 'resolved_plan_local'
                    && $external === $planLocal) {
                    $match = $observedCity;
                    break;
                }
            }

            if ($match === null) {
                $rows[] = [
                    'planned' => $plannedCity,
                    'observed' => null,
                    'status' => $this->absenceProven($plannedCity, $observation, $dataset)
                        ? 'missing'
                        : 'not_observed',
                    'distance_tiles' => null,
                    'delta_x' => null,
                    'delta_y' => null,
                    'planned_covered' => $plannedCoverage[(string) $plannedCity['key']] ?? null,
                    'observed_covered' => null,
                    'coverage_delta' => 'unknown',
                ];

                continue;
            }

            $key = (string) $match['key'];
            $used[] = $key;
            $dx = (int) $match['x'] - (int) $plannedCity['x'];
            $dy = (int) $match['y'] - (int) $plannedCity['y'];
            $distance = hypot($dx, $dy);
            $plannedIsCovered = $plannedCoverage[(string) $plannedCity['key']] ?? null;
            $observedIsCovered = $observedCoverage[$key] ?? null;
            $rows[] = [
                'planned' => $plannedCity,
                'observed' => $match,
                'status' => $distance <= $tolerance ? 'in_position' : 'out_of_position',
                'distance_tiles' => round($distance, 2),
                'delta_x' => $dx,
                'delta_y' => $dy,
                'planned_covered' => $plannedIsCovered,
                'observed_covered' => $observedIsCovered,
                'coverage_delta' => $this->coverageDelta($plannedIsCovered, $observedIsCovered),
            ];
        }

        return [$rows, $used];
    }

    private function coverageDelta(?bool $planned, ?bool $observed): string
    {
        if ($planned === null || $observed === null) {
            return 'unknown';
        }
        if ($planned && ! $observed) {
            return 'lost_coverage';
        }
        if (! $planned && $observed) {
            return 'gained_coverage';
        }

        return 'unchanged';
    }

    /**
     * @param  list<array<string, mixed>>  $planned
     * @param  list<array<string, mixed>>  $observed
     * @param  array<string, mixed>  $observation
     * @param  array<string, mixed>  $dataset
     * @return array{0:list<array<string,mixed>>,1:list<string>}
     */
    private function structures(
        array $planned,
        array $observed,
        array $observation,
        array $dataset,
    ): array {
        $types = ['headquarters', 'bear_trap', 'banner'];
        $rows = [];
        $used = [];
        foreach ($types as $type) {
            $plannedRows = $this->objectsOfType($planned, $type);
            $observedRows = $this->objectsOfType($observed, $type);
            foreach ($plannedRows as $plannedObject) {
                [$match, $ambiguousKeys] = $this->nearest(
                    $plannedObject,
                    $observedRows,
                    $used,
                    $type === 'banner'
                        ? (float) config('territory_reconciliation.banner_match_max_tiles', 25.0)
                        : INF,
                );
                if ($ambiguousKeys !== []) {
                    $used = array_values(array_unique([...$used, ...$ambiguousKeys]));
                    $rows[] = [
                        'planned' => $plannedObject,
                        'observed' => null,
                        'status' => 'ambiguous',
                        'distance_tiles' => null,
                        'delta_x' => null,
                        'delta_y' => null,
                        'ambiguous_observed_keys' => $ambiguousKeys,
                    ];

                    continue;
                }

                if ($match === null) {
                    $rows[] = [
                        'planned' => $plannedObject,
                        'observed' => null,
                        'status' => $this->absenceProven($plannedObject, $observation, $dataset)
                            ? 'missing'
                            : 'not_observed',
                        'distance_tiles' => null,
                        'delta_x' => null,
                        'delta_y' => null,
                    ];

                    continue;
                }

                $key = (string) $match['key'];
                $used[] = $key;
                $dx = (int) $match['x'] - (int) $plannedObject['x'];
                $dy = (int) $match['y'] - (int) $plannedObject['y'];
                $distance = hypot($dx, $dy);
                $rows[] = [
                    'planned' => $plannedObject,
                    'observed' => $match,
                    'status' => $distance <= 0.000001 ? 'unchanged' : 'moved',
                    'distance_tiles' => round($distance, 2),
                    'delta_x' => $dx,
                    'delta_y' => $dy,
                ];
            }
        }

        return [$rows, $used];
    }

    /**
     * @param  list<array<string, mixed>>  $objects
     * @return list<array<string, mixed>>
     */
    private function objectsOfType(array $objects, string $type): array
    {
        return array_values(array_filter(
            $objects,
            static fn (array $object): bool => ($object['type'] ?? null) === $type,
        ));
    }

    /**
     * @param  array<string, mixed>  $planned
     * @param  list<array<string, mixed>>  $candidates
     * @param  list<string>  $used
     * @return array{0:?array<string,mixed>,1:list<string>}
     */
    private function nearest(array $planned, array $candidates, array $used, float $max): array
    {
        $best = null;
        $bestDistance = null;
        $nearestKeys = [];
        foreach ($candidates as $candidate) {
            $key = (string) ($candidate['key'] ?? '');
            if ($key === '' || in_array($key, $used, true)) {
                continue;
            }

            $distance = hypot(
                (int) $candidate['x'] - (int) $planned['x'],
                (int) $candidate['y'] - (int) $planned['y'],
            );
            if ($distance > $max) {
                continue;
            }

            if ($bestDistance === null || $distance < $bestDistance - 0.000001) {
                $best = $candidate;
                $bestDistance = $distance;
                $nearestKeys = [$key];
            } elseif (abs($distance - $bestDistance) <= 0.000001) {
                $nearestKeys[] = $key;
            }
        }

        if (count($nearestKeys) > 1) {
            return [null, $nearestKeys];
        }

        return [$best, []];
    }

    /**
     * @param  array<string, mixed>  $planned
     * @param  array<string, mixed>  $observation
     * @param  array<string, mixed>  $dataset
     */
    private function absenceProven(array $planned, array $observation, array $dataset): bool
    {
        if (($observation['completeness'] ?? null) !== 'complete') {
            return false;
        }
        if (($observation['coverage_kind'] ?? null) === 'complete_hive') {
            return true;
        }
        if (($observation['coverage_kind'] ?? null) !== 'complete_visible_region'
            || ! is_array($observation['coverage_bounds'] ?? null)) {
            return false;
        }

        $bounds = $observation['coverage_bounds'];
        $definition = is_array($dataset['object_types'][$planned['type']] ?? null)
            ? $dataset['object_types'][$planned['type']]
            : [];
        $size = max(1, (int) ($definition['size'] ?? 1));

        return (int) $planned['x'] >= (int) $bounds['x']
            && (int) $planned['y'] >= (int) $bounds['y']
            && (int) $planned['x'] + $size <= (int) $bounds['x'] + (int) $bounds['width']
            && (int) $planned['y'] + $size <= (int) $bounds['y'] + (int) $bounds['height'];
    }

    /**
     * @param  list<array<string, mixed>>  $governors
     * @param  list<array<string, mixed>>  $structures
     * @param  list<array<string, mixed>>  $unexpected
     * @return array<string, int>
     */
    private function summary(array $governors, array $structures, array $unexpected): array
    {
        $summary = [
            'governors_total' => count($governors),
            'in_position' => 0,
            'out_of_position' => 0,
            'missing' => 0,
            'not_observed' => 0,
            'unexpected' => count($unexpected),
            'structures_changed' => 0,
            'lost_coverage' => 0,
        ];
        foreach ($governors as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
            if (($row['coverage_delta'] ?? null) === 'lost_coverage') {
                $summary['lost_coverage']++;
            }
        }
        foreach ($structures as $row) {
            if (in_array($row['status'] ?? null, ['moved', 'missing'], true)) {
                $summary['structures_changed']++;
            }
        }

        return $summary;
    }
}
