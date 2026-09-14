<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;

final readonly class TerritoryPlanImport
{
    public function __construct(
        private KingdomMapDatasetQuery $datasets,
        private PlacementValidator $placement,
        private TerritoryLayoutAnalyzer $analysis,
        private TerritoryLayoutContract $contract,
    ) {}

    /** @return array<string, mixed> */
    public function preview(string $json): array
    {
        $document = $this->contract->decode($json);
        $plan = $document['plan'];
        $dataset = $this->datasets->require($plan['map_dataset_id'], $plan['map_dataset_checksum']);
        $preferences = $plan['planning_preferences'];
        $validationObjects = array_map(
            static fn (array $object): array => [
                'key' => $object['key'], 'type' => $object['type'],
                'x' => $object['x'], 'y' => $object['y'],
                'alliance_key' => $object['alliance_key'],
                'rotation' => $object['rotation'],
                'variant_key' => $object['metadata']['variant_key'] ?? null,
            ],
            $document['objects'],
        );
        $validation = $this->placement->validate($dataset, $validationObjects, $preferences);

        return [
            'schema_version' => TerritoryLayoutContract::SCHEMA_VERSION,
            'document_checksum' => hash('sha256', $json),
            'map' => ['id' => $dataset->id, 'checksum' => $dataset->checksum,
                'source_label' => $dataset->sourceLabel, 'confidence' => $dataset->confidence->value],
            'alliances' => $document['alliances'], 'groups' => $document['groups'],
            'objects' => $document['objects'], 'planning_preferences' => $preferences,
            'validation' => $validation->toArray(),
            'analysis' => $this->analysis->analyze($dataset, $validationObjects, $preferences),
            'can_commit' => $validation->valid(),
        ];
    }
}
