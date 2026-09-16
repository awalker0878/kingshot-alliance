<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use Illuminate\Validation\ValidationException;

final readonly class TerritoryPlanImport
{
    public function __construct(
        private KingdomMapDatasetQuery $datasets,
        private PlacementValidator $placement,
        private TerritoryLayoutAnalyzer $analysis,
        private TerritoryLayoutDocumentContract $contract,
    ) {}

    /** @return array<string, mixed> */
    public function preview(string $json): array
    {
        $document = $this->contract->decode($json);
        $plan = $document['plan'];
        $dataset = $this->datasets->require($plan['map_dataset_id'], $plan['map_dataset_checksum']);
        $preferences = $plan['planning_preferences'];
        $validationObjects = $this->validationObjects($document['objects'] ?? null);
        $validation = $this->placement->validate($dataset, $validationObjects, $preferences);

        return [
            'schema_version' => TerritoryLayoutContract::SCHEMA_VERSION,
            'document_checksum' => hash('sha256', $json),
            'map' => [
                'id' => $dataset->id,
                'checksum' => $dataset->checksum,
                'source_label' => $dataset->sourceLabel,
                'confidence' => $dataset->confidence->value,
            ],
            'alliances' => $document['alliances'],
            'groups' => $document['groups'],
            'objects' => $document['objects'],
            'annotations' => $document['annotations'],
            'planning_preferences' => $preferences,
            'validation' => $validation->toArray(),
            'analysis' => $this->analysis->analyze($dataset, $validationObjects, $preferences),
            'can_commit' => $validation->valid(),
        ];
    }

    /**
     * @return list<array{key:string,type:string,x:int,y:int,alliance_key:string,rotation:int,variant_key:?string}>
     */
    private function validationObjects(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->invalidObjects();
        }

        $objects = [];
        foreach ($value as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw $this->invalidObjects();
            }
            $key = $row['key'] ?? null;
            $type = $row['type'] ?? null;
            $x = $row['x'] ?? null;
            $y = $row['y'] ?? null;
            $allianceKey = $row['alliance_key'] ?? null;
            $rotation = $row['rotation'] ?? null;
            $metadata = $row['metadata'] ?? null;
            if (! is_string($key) || ! is_string($type)
                || ! is_int($x) || ! is_int($y)
                || ! is_string($allianceKey) || ! is_int($rotation)
                || ! is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
                throw $this->invalidObjects();
            }
            $variantKey = $metadata['variant_key'] ?? null;
            if ($variantKey !== null && ! is_string($variantKey)) {
                throw $this->invalidObjects();
            }

            $objects[] = [
                'key' => $key,
                'type' => $type,
                'x' => $x,
                'y' => $y,
                'alliance_key' => $allianceKey,
                'rotation' => $rotation,
                'variant_key' => $variantKey,
            ];
        }

        return $objects;
    }

    private function invalidObjects(): ValidationException
    {
        return ValidationException::withMessages([
            'import' => 'The normalized Territory layout contains invalid object data.',
        ]);
    }
}
