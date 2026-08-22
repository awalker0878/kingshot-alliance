<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use Illuminate\Validation\ValidationException;
use JsonException;

final readonly class TerritoryPlanImport
{
    public function __construct(
        private KingdomMapDatasetQuery $datasets,
        private PlacementValidator $placement,
        private TerritoryLayoutAnalyzer $analysis,
    ) {}

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
        if (! is_array($plan)) {
            throw $this->invalid('The layout document is missing required plan data.');
        }
        $alliances = $this->rows($document['alliances'] ?? null, 'Alliance');
        $groups = $this->rows($document['groups'] ?? null, 'group');
        $objects = $this->rows($document['objects'] ?? null, 'object');

        $datasetId = (string) ($plan['map_dataset_id'] ?? '');
        $datasetChecksum = (string) ($plan['map_dataset_checksum'] ?? '');
        $dataset = $this->datasets->require($datasetId, $datasetChecksum === '' ? null : $datasetChecksum);
        $preferences = is_array($plan['planning_preferences'] ?? null) ? $plan['planning_preferences'] : [];
        $this->assertStructuralContract($dataset->data['object_types'] ?? [], $alliances, $groups, $objects, $preferences);

        $validationObjects = array_map(
            static fn (array $object): array => [
                'key' => (string) $object['key'],
                'type' => (string) $object['type'],
                'x' => (int) $object['x'],
                'y' => (int) $object['y'],
                'alliance_key' => (string) $object['alliance_key'],
            ],
            $objects,
        );
        $validation = $this->placement->validate($dataset, $validationObjects, $preferences);

        return [
            'schema_version' => 1,
            'map' => [
                'id' => $dataset->id,
                'checksum' => $dataset->checksum,
                'source_label' => $dataset->sourceLabel,
                'confidence' => $dataset->confidence->value,
            ],
            'alliances' => $alliances,
            'groups' => $groups,
            'objects' => $objects,
            'planning_preferences' => $preferences,
            'validation' => $validation->toArray(),
            'analysis' => $this->analysis->analyze($dataset, $validationObjects, $preferences),
            'can_commit' => $validation->valid(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $value, string $label): array
    {
        if (! is_array($value)) {
            throw $this->invalid("The layout document is missing required {$label} data.");
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                throw $this->invalid("Every imported {$label} entry must be an object.");
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  mixed  $objectTypes
     * @param  list<array<string, mixed>>  $alliances
     * @param  list<array<string, mixed>>  $groups
     * @param  list<array<string, mixed>>  $objects
     * @param  array<string, mixed>  $preferences
     */
    private function assertStructuralContract(
        mixed $objectTypes,
        array $alliances,
        array $groups,
        array $objects,
        array $preferences,
    ): void {
        if (! is_array($objectTypes) || $alliances === [] || count($alliances) > 50 || count($groups) > 500 || count($objects) > 5000) {
            throw $this->invalid('The imported Territory layout exceeds the supported structural limits.');
        }

        $allianceKeys = [];
        foreach ($alliances as $alliance) {
            $key = $this->stringOrNull($alliance['key'] ?? null);
            $displayName = $this->stringOrNull($alliance['display_name'] ?? null);
            $linkedId = $this->stringOrNull($alliance['alliance_id'] ?? null);
            $externalName = $this->stringOrNull($alliance['external_name'] ?? null);
            $color = strtolower((string) ($alliance['presentation_color'] ?? ''));
            if (
                $key === null
                || isset($allianceKeys[$key])
                || $displayName === null
                || ($linkedId === null) === ($externalName === null)
                || ! preg_match('/^#[0-9a-f]{6}$/', $color)
            ) {
                throw $this->invalid('An imported Alliance layer has invalid identity, display, or presentation data.');
            }
            $allianceKeys[$key] = true;
        }

        $groupKeys = [];
        foreach ($groups as $group) {
            $key = $this->stringOrNull($group['key'] ?? null);
            if ($key === null || isset($groupKeys[$key])) {
                throw $this->invalid('Every imported group requires a unique valid key.');
            }
            $groupKeys[$key] = true;
        }

        $objectKeys = [];
        $bearTrapsByAlliance = [];
        foreach ($objects as $object) {
            $key = $this->stringOrNull($object['key'] ?? null);
            $type = $this->stringOrNull($object['type'] ?? null);
            $allianceKey = $this->stringOrNull($object['alliance_key'] ?? null);
            $groupKey = $this->stringOrNull($object['group_key'] ?? null);
            $rotation = $object['rotation'] ?? 0;
            if (
                $key === null
                || isset($objectKeys[$key])
                || $type === null
                || ! isset($objectTypes[$type])
                || $allianceKey === null
                || ! isset($allianceKeys[$allianceKey])
                || ($groupKey !== null && ! isset($groupKeys[$groupKey]))
                || ! is_int($object['x'] ?? null)
                || ! is_int($object['y'] ?? null)
                || ! is_int($rotation)
                || ! in_array($rotation, [0, 90, 180, 270], true)
            ) {
                throw $this->invalid('An imported planned object has invalid identity, coordinates, type, rotation, or layer references.');
            }

            $playerId = $this->stringOrNull($object['player_id'] ?? null);
            $externalPlayer = $this->stringOrNull($object['external_player_name'] ?? null);
            if (($playerId !== null && $externalPlayer !== null) || ($type !== 'governor_city' && ($playerId !== null || $externalPlayer !== null))) {
                throw $this->invalid('Imported Governor identity may only be assigned to a Governor city and cannot be both linked and external.');
            }

            $objectKeys[$key] = true;
            if ($type === 'bear_trap') {
                $bearTrapsByAlliance[$allianceKey][$key] = true;
            }
        }

        $selectedTraps = $preferences['selected_bear_trap_by_alliance'] ?? null;
        if ($selectedTraps !== null) {
            if (! is_array($selectedTraps)) {
                throw $this->invalid('Imported selected Bear Traps must be keyed by Alliance layer.');
            }
            foreach ($selectedTraps as $allianceKey => $trapKey) {
                if (! is_string($allianceKey) || ! is_string($trapKey) || ! isset($bearTrapsByAlliance[$allianceKey][$trapKey])) {
                    throw $this->invalid('An imported selected Bear Trap must belong to its Alliance layer.');
                }
            }
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['import' => $message]);
    }
}
