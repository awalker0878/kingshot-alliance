<?php

declare(strict_types=1);

namespace App\ReadModels\TerritoryPlanning\Queries;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanRevisionQuery;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCoverageAnalyzer;
use App\Contexts\Intelligence\Observations\Queries\SpatialObservationQuery;
use Carbon\CarbonImmutable;

final readonly class TerritoryReconciliationQuery
{
    public function __construct(
        private TerritoryPlanQuery $plans,
        private TerritoryPlanRevisionQuery $revisions,
        private SpatialObservationQuery $observations,
        private KingdomMapDatasetQuery $datasets,
        private TerritoryCoverageAnalyzer $coverage,
    ) {}

    /** @return array<string,mixed> */
    public function build(
        string $actorPlayerId,
        string $planId,
        ?string $revisionId = null,
        ?string $allianceId = null,
        ?string $observationId = null,
    ): array {
        $detail = $this->plans->detail($actorPlayerId, $planId);
        $plan = is_array($detail['plan'] ?? null) ? $detail['plan'] : [];
        $revisionOptions = is_array($detail['revisions'] ?? null) ? $detail['revisions'] : [];
        usort($revisionOptions, static fn (array $a, array $b): int => ((int) ($b['revision_number'] ?? 0)) <=> ((int) ($a['revision_number'] ?? 0)));
        if ($revisionOptions === []) {
            return ['state' => 'no_published_revision', 'plan' => $plan, 'revisions' => []];
        }
        $revisionId ??= is_string($revisionOptions[0]['id'] ?? null) ? $revisionOptions[0]['id'] : null;
        if ($revisionId === null) {
            return ['state' => 'no_published_revision', 'plan' => $plan, 'revisions' => $revisionOptions];
        }
        $revision = $this->revisions->snapshot($actorPlayerId, $planId, $revisionId);
        $snapshot = is_array($revision['snapshot'] ?? null) ? $revision['snapshot'] : [];
        $snapshotPlan = is_array($snapshot['plan'] ?? null) ? $snapshot['plan'] : [];
        $layers = is_array($snapshot['alliances'] ?? null) ? $snapshot['alliances'] : [];
        $allianceOptions = [];
        foreach ($layers as $layer) {
            if (is_array($layer) && is_string($layer['alliance_id'] ?? null)) {
                $allianceOptions[] = [
                    'id' => $layer['alliance_id'],
                    'key' => (string) ($layer['key'] ?? ''),
                    'name' => (string) ($layer['display_name'] ?? $layer['alliance_id']),
                ];
            }
        }
        $allianceOptions = array_values(array_filter($allianceOptions, static fn (array $row): bool => $row['key'] !== ''));
        if (($snapshotPlan['scope'] ?? null) === 'alliance') {
            $allianceId = is_string($snapshotPlan['owner_alliance_id'] ?? null) ? $snapshotPlan['owner_alliance_id'] : $allianceId;
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
        $layer = null;
        foreach ($allianceOptions as $option) {
            if ($option['id'] === $allianceId) {
                $layer = $option;
                break;
            }
        }
        $kingdomId = (string) ($snapshotPlan['kingdom_id'] ?? $plan['kingdom_id'] ?? '');
        $history = $this->observations->history($actorPlayerId, $allianceId, $kingdomId, 50);
        $observation = $observationId === null
            ? $this->observations->latest($actorPlayerId, $allianceId, $kingdomId)
            : $this->observations->detail($actorPlayerId, $allianceId, $kingdomId, $observationId);
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

        $planDataset = $this->datasets->require((string) $revision['map_dataset_id'], (string) $revision['map_dataset_checksum']);
        $observedDataset = $this->datasets->require((string) $observation['map_dataset_id'], (string) $observation['map_dataset_checksum']);
        $compatibility = $this->compatibility($planDataset->data, $observedDataset->data, $planDataset->id, $observedDataset->id, $planDataset->checksum, $observedDataset->checksum);
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
            ];
        }

        $plannedObjects = array_values(array_filter(
            is_array($snapshot['objects'] ?? null) ? $snapshot['objects'] : [],
            static fn ($object): bool => is_array($object) && ($object['alliance_key'] ?? null) === ($layer['key'] ?? null),
        ));
        $observedObjects = is_array($observation['objects'] ?? null) ? $observation['objects'] : [];
        $plannedCoverage = $this->coverage->byGovernorCity($planDataset, $this->coverageObjects($plannedObjects, (string) $layer['key']));
        $observedCoverage = $this->coverage->byGovernorCity($observedDataset, $this->coverageObjects($observedObjects, (string) $layer['key']));

        [$governors, $usedObservedGovernorKeys] = $this->governors($plannedObjects, $observedObjects, $observation, $planDataset->data, $plannedCoverage, $observedCoverage);
        [$structures, $usedObservedStructureKeys] = $this->structures($plannedObjects, $observedObjects, $observation, $planDataset->data);
        $unexpected = [];
        foreach ($observedObjects as $object) {
            if (! is_array($object)) {
                continue;
            }
            $key = (string) ($object['key'] ?? '');
            if (in_array($key, $usedObservedGovernorKeys, true) || in_array($key, $usedObservedStructureKeys, true)) {
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
            $unexpected[] = $object + ['status' => $status, 'observed_covered' => $type === 'governor_city' ? ($observedCoverage[$key] ?? null) : null];
        }

        $summary = $this->summary($governors, $structures, $unexpected);
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
            'summary' => $summary,
            'governors' => $governors,
            'structures' => $structures,
            'unexpected' => $unexpected,
            'planned_objects' => $plannedObjects,
            'observed_objects' => $observedObjects,
            'policy' => [
                'position_tolerance_tiles' => (float) config('territory_reconciliation.position_tolerance_tiles', 1.0),
                'banner_match_max_tiles' => (float) config('territory_reconciliation.banner_match_max_tiles', 25.0),
            ],
        ];
    }

    /** @param array<string,mixed> $planDataset @param array<string,mixed> $observedDataset */
    private function compatibility(array $planDataset, array $observedDataset, string $planId, string $observedId, string $planChecksum, string $observedChecksum): string
    {
        if ($planId === $observedId && hash_equals($planChecksum, $observedChecksum)) {
            return 'same_dataset';
        }
        $planCoordinate = $planDataset['coordinate_system'] ?? null;
        $observedCoordinate = $observedDataset['coordinate_system'] ?? null;
        return is_array($planCoordinate) && is_array($observedCoordinate) && $planCoordinate === $observedCoordinate
            ? 'compatible_release'
            : 'dataset_incompatible';
    }

    /** @return array{age_seconds:int,state:string,fresh_seconds:int,aging_seconds:int} */
    private function freshness(string $capturedAt): array
    {
        $captured = CarbonImmutable::parse($capturedAt)->utc();
        $age = max(0, $captured->diffInSeconds(CarbonImmutable::now('UTC')));
        $fresh = max(1, (int) config('territory_reconciliation.fresh_seconds', 3600));
        $aging = max($fresh, (int) config('territory_reconciliation.aging_seconds', 21600));
        return [
            'age_seconds' => $age,
            'state' => $age <= $fresh ? 'fresh' : ($age <= $aging ? 'aging' : 'stale'),
            'fresh_seconds' => $fresh,
            'aging_seconds' => $aging,
        ];
    }

    /** @param list<array<string,mixed>> $objects @return list<array{key:string,type:string,x:int,y:int,alliance_key:string}> */
    private function coverageObjects(array $objects, string $allianceKey): array
    {
        $result = [];
        foreach ($objects as $object) {
            if (! is_array($object) || ! is_string($object['key'] ?? null) || ! is_string($object['type'] ?? null) || ! is_int($object['x'] ?? null) || ! is_int($object['y'] ?? null)) {
                continue;
            }
            $result[] = ['key' => $object['key'], 'type' => $object['type'], 'x' => $object['x'], 'y' => $object['y'], 'alliance_key' => $allianceKey];
        }
        return $result;
    }

    /** @return array{0:list<array<string,mixed>>,1:list<string>} */
    private function governors(array $planned, array $observed, array $observation, array $dataset, array $plannedCoverage, array $observedCoverage): array
    {
        $plannedCities = array_values(array_filter($planned, static fn ($o): bool => is_array($o) && ($o['type'] ?? null) === 'governor_city'));
        $observedCities = array_values(array_filter($observed, static fn ($o): bool => is_array($o) && ($o['type'] ?? null) === 'governor_city'));
        $used = [];
        $rows = [];
        $tolerance = max(0.0, (float) config('territory_reconciliation.position_tolerance_tiles', 1.0));
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
                $external = is_string($plannedCity['external_player_name'] ?? null) ? trim(mb_strtolower($plannedCity['external_player_name'])) : '';
                $planLocal = is_string($observedCity['plan_local_identity'] ?? null) ? trim(mb_strtolower($observedCity['plan_local_identity'])) : '';
                if ($external !== '' && ($observedCity['identity_state'] ?? null) === 'resolved_plan_local' && $external === $planLocal) {
                    $match = $observedCity;
                    break;
                }
            }
            if ($match === null) {
                $status = $this->absenceProven($plannedCity, $observation, $dataset) ? 'missing' : 'not_observed';
                $rows[] = [
                    'planned' => $plannedCity,
                    'observed' => null,
                    'status' => $status,
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
                'coverage_delta' => $plannedIsCovered === true && $observedIsCovered === false ? 'lost_coverage' : ($plannedIsCovered === false && $observedIsCovered === true ? 'gained_coverage' : 'unchanged'),
            ];
        }
        return [$rows, $used];
    }

    /** @return array{0:list<array<string,mixed>>,1:list<string>} */
    private function structures(array $planned, array $observed, array $observation, array $dataset): array
    {
        $types = ['headquarters', 'bear_trap', 'banner'];
        $rows = [];
        $used = [];
        foreach ($types as $type) {
            $plannedRows = array_values(array_filter($planned, static fn ($o): bool => is_array($o) && ($o['type'] ?? null) === $type));
            $observedRows = array_values(array_filter($observed, static fn ($o): bool => is_array($o) && ($o['type'] ?? null) === $type));
            foreach ($plannedRows as $plannedObject) {
                $match = $this->nearest($plannedObject, $observedRows, $used, $type === 'banner' ? (float) config('territory_reconciliation.banner_match_max_tiles', 25.0) : INF);
                if ($match === null) {
                    $rows[] = ['planned' => $plannedObject, 'observed' => null, 'status' => $this->absenceProven($plannedObject, $observation, $dataset) ? 'missing' : 'not_observed', 'distance_tiles' => null, 'delta_x' => null, 'delta_y' => null];
                    continue;
                }
                $key = (string) $match['key'];
                $used[] = $key;
                $dx = (int) $match['x'] - (int) $plannedObject['x'];
                $dy = (int) $match['y'] - (int) $plannedObject['y'];
                $distance = hypot($dx, $dy);
                $rows[] = ['planned' => $plannedObject, 'observed' => $match, 'status' => $distance <= 0.000001 ? 'unchanged' : 'moved', 'distance_tiles' => round($distance, 2), 'delta_x' => $dx, 'delta_y' => $dy];
            }
        }
        return [$rows, $used];
    }

    /** @param list<array<string,mixed>> $candidates @param list<string> $used */
    private function nearest(array $planned, array $candidates, array $used, float $max): ?array
    {
        $best = null;
        $bestDistance = null;
        $tie = false;
        foreach ($candidates as $candidate) {
            $key = (string) ($candidate['key'] ?? '');
            if ($key === '' || in_array($key, $used, true)) {
                continue;
            }
            $distance = hypot((int) $candidate['x'] - (int) $planned['x'], (int) $candidate['y'] - (int) $planned['y']);
            if ($distance > $max) {
                continue;
            }
            if ($bestDistance === null || $distance < $bestDistance - 0.000001) {
                $best = $candidate;
                $bestDistance = $distance;
                $tie = false;
            } elseif (abs($distance - $bestDistance) <= 0.000001) {
                $tie = true;
            }
        }
        return $tie ? null : $best;
    }

    /** @param array<string,mixed> $planned @param array<string,mixed> $observation @param array<string,mixed> $dataset */
    private function absenceProven(array $planned, array $observation, array $dataset): bool
    {
        if (($observation['completeness'] ?? null) !== 'complete') {
            return false;
        }
        if (($observation['coverage_kind'] ?? null) === 'complete_hive') {
            return true;
        }
        if (($observation['coverage_kind'] ?? null) !== 'complete_visible_region' || ! is_array($observation['coverage_bounds'] ?? null)) {
            return false;
        }
        $bounds = $observation['coverage_bounds'];
        $definition = is_array($dataset['object_types'][$planned['type']] ?? null) ? $dataset['object_types'][$planned['type']] : [];
        $size = max(1, (int) ($definition['size'] ?? 1));
        return (int) $planned['x'] >= (int) $bounds['x']
            && (int) $planned['y'] >= (int) $bounds['y']
            && (int) $planned['x'] + $size <= (int) $bounds['x'] + (int) $bounds['width']
            && (int) $planned['y'] + $size <= (int) $bounds['y'] + (int) $bounds['height'];
    }

    /** @return array<string,int> */
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
            if (($row['status'] ?? null) === 'moved' || ($row['status'] ?? null) === 'missing') {
                $summary['structures_changed']++;
            }
        }
        return $summary;
    }
}
