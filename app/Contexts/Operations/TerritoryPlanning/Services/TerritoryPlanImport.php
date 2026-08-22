<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use Illuminate\Validation\ValidationException;
use JsonException;

final readonly class TerritoryPlanImport
{
    public function __construct(private KingdomMapDatasetQuery $datasets, private PlacementValidator $placement, private TerritoryLayoutAnalyzer $analysis) {}

    /** @return array<string,mixed> */
    public function preview(string $json): array
    {
        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['import' => 'The selected layout is not valid JSON.']);
        }
        if (! is_array($document) || (int) ($document['schema_version'] ?? 0) !== 1) {
            throw ValidationException::withMessages(['import' => 'Only Territory Layout schema version 1 is supported.']);
        }
        $plan = $document['plan'] ?? null;
        $alliances = $document['alliances'] ?? null;
        $groups = $document['groups'] ?? null;
        $objects = $document['objects'] ?? null;
        if (! is_array($plan) || ! is_array($alliances) || ! is_array($groups) || ! is_array($objects)) {
            throw ValidationException::withMessages(['import' => 'The layout document is missing required plan, Alliance, group, or object data.']);
        }
        $datasetId = (string) ($plan['map_dataset_id'] ?? '');
        $datasetChecksum = (string) ($plan['map_dataset_checksum'] ?? '');
        $dataset = $this->datasets->require($datasetId, $datasetChecksum === '' ? null : $datasetChecksum);
        $validationObjects = [];
        foreach ($objects as $object) {
            if (! is_array($object)) {
                continue;
            }
            $validationObjects[] = [
                'key' => (string) ($object['key'] ?? ''),
                'type' => (string) ($object['type'] ?? ''),
                'x' => (int) ($object['x'] ?? -1),
                'y' => (int) ($object['y'] ?? -1),
                'alliance_key' => (string) ($object['alliance_key'] ?? ''),
            ];
        }
        $preferences = is_array($plan['planning_preferences'] ?? null) ? $plan['planning_preferences'] : [];
        $validation = $this->placement->validate($dataset, $validationObjects, $preferences);

        return [
            'schema_version' => 1,
            'map' => ['id' => $dataset->id, 'checksum' => $dataset->checksum, 'source_label' => $dataset->sourceLabel, 'confidence' => $dataset->confidence->value],
            'alliances' => $alliances,
            'groups' => $groups,
            'objects' => $objects,
            'planning_preferences' => $preferences,
            'validation' => $validation->toArray(),
            'analysis' => $this->analysis->analyze($dataset, $validationObjects, $preferences),
            'can_commit' => $validation->valid(),
        ];
    }
}
