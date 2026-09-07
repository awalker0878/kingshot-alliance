<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use App\Contexts\GameWorld\KingdomMaps\Enums\MapDatasetConfidence;
use RuntimeException;

final class KingdomMapSchemaV2Validator
{
    /** @param array<string,mixed> $data */
    public function validate(array $data): void
    {
        $this->requireString($data, 'id');
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{1,119}$/', $data['id'])) {
            $this->fail('id must be a stable lowercase release identifier.');
        }
        if (($data['schema_version'] ?? null) !== 2) {
            $this->fail('schema_version must be exactly 2.');
        }
        if (($data['release_status'] ?? null) !== 'released') {
            $this->fail('release_status must be released for runtime datasets.');
        }
        $this->requireString($data, 'released_at');
        $this->requireString($data, 'observed_at');
        $this->requireString($data, 'title');
        $this->confidence($data['confidence'] ?? null, 'confidence');

        foreach (['game_version', 'season', 'predecessor_id', 'summary'] as $nullableString) {
            if (array_key_exists($nullableString, $data)
                && $data[$nullableString] !== null
                && ! is_string($data[$nullableString])) {
                $this->fail($nullableString.' must be a string or null.');
            }
        }

        $sources = $this->requireMap($data, 'sources');
        if ($sources === []) {
            $this->fail('sources must contain at least one provenance source.');
        }
        foreach ($sources as $sourceId => $source) {
            if (! is_string($sourceId) || ! preg_match('/^[a-z0-9][a-z0-9._-]{1,79}$/', $sourceId)) {
                $this->fail('source IDs must be stable lowercase identifiers.');
            }
            if (! is_array($source)) {
                $this->fail('source '.$sourceId.' must be an object.');
            }
            foreach (['type', 'label', 'uri', 'rights_basis', 'lineage'] as $key) {
                $this->requireString($source, $key, 'sources.'.$sourceId.'.');
            }
            $this->confidence($source['confidence_ceiling'] ?? null, 'sources.'.$sourceId.'.confidence_ceiling');
        }

        $coordinateSystem = $this->requireMap($data, 'coordinate_system');
        $this->requireString($coordinateSystem, 'name', 'coordinate_system.');
        $this->requireString($coordinateSystem, 'origin', 'coordinate_system.');
        if (! is_int($coordinateSystem['tile_size'] ?? null) || $coordinateSystem['tile_size'] < 1) {
            $this->fail('coordinate_system.tile_size must be a positive integer.');
        }
        $this->provenance($coordinateSystem, $sources, 'coordinate_system');

        $bounds = $this->requireMap($data, 'bounds');
        foreach (['x', 'y'] as $key) {
            if (! is_int($bounds[$key] ?? null)) {
                $this->fail('bounds.'.$key.' must be an integer.');
            }
        }
        foreach (['width', 'height'] as $key) {
            if (! is_int($bounds[$key] ?? null) || $bounds[$key] < 1) {
                $this->fail('bounds.'.$key.' must be a positive integer.');
            }
        }
        $this->provenance($bounds, $sources, 'bounds');

        $objectTypes = $this->requireMap($data, 'object_types');
        if ($objectTypes === []) {
            $this->fail('object_types must not be empty.');
        }
        foreach ($objectTypes as $objectType => $definition) {
            if (! is_string($objectType) || ! is_array($definition)) {
                $this->fail('object type definitions must be keyed objects.');
            }
            $this->footprint($definition['footprint'] ?? null, 'object_types.'.$objectType.'.footprint');
            $coverage = $definition['coverage'] ?? null;
            if ($coverage !== null) {
                $this->footprint($coverage, 'object_types.'.$objectType.'.coverage');
            }
            $maximum = $definition['max_per_alliance'] ?? null;
            if ($maximum !== null && (! is_int($maximum) || $maximum < 1)) {
                $this->fail('object_types.'.$objectType.'.max_per_alliance must be a positive integer or null.');
            }
            $this->provenance($definition, $sources, 'object_types.'.$objectType);
            if (isset($definition['variants'])) {
                if (! is_array($definition['variants']) || ! array_is_list($definition['variants'])) {
                    $this->fail('object_types.'.$objectType.'.variants must be a list.');
                }
                $variantKeys = [];
                foreach ($definition['variants'] as $index => $variant) {
                    if (! is_array($variant)) {
                        $this->fail('object variant must be an object.');
                    }
                    $this->requireString($variant, 'key', 'object_types.'.$objectType.'.variants.'.$index.'.');
                    if (isset($variantKeys[$variant['key']])) {
                        $this->fail('object variant keys must be unique.');
                    }
                    $variantKeys[$variant['key']] = true;
                    foreach (['allowed_zones', 'prerequisites'] as $listKey) {
                        if (! is_array($variant[$listKey] ?? null) || ! array_is_list($variant[$listKey])) {
                            $this->fail('object variant '.$listKey.' must be a list.');
                        }
                        foreach ($variant[$listKey] as $value) {
                            if (! is_string($value) || $value === '') {
                                $this->fail('object variant '.$listKey.' values must be non-empty strings.');
                            }
                        }
                    }
                    $this->provenance($variant, $sources, 'object_types.'.$objectType.'.variants.'.$index);
                }
            }
        }

        $zones = $this->requireMap($data, 'zones');
        foreach ($zones as $zoneKey => $zone) {
            if (! is_string($zoneKey) || ! is_array($zone)) {
                $this->fail('zones must be keyed objects.');
            }
            foreach (['x', 'y'] as $key) {
                if (! is_int($zone[$key] ?? null)) {
                    $this->fail('zones.'.$zoneKey.'.'.$key.' must be an integer.');
                }
            }
            foreach (['width', 'height'] as $key) {
                if (! is_int($zone[$key] ?? null) || $zone[$key] < 1) {
                    $this->fail('zones.'.$zoneKey.'.'.$key.' must be a positive integer.');
                }
            }
            if (! is_array($zone['blocked_types'] ?? null) || ! array_is_list($zone['blocked_types'])) {
                $this->fail('zones.'.$zoneKey.'.blocked_types must be a list.');
            }
            foreach ($zone['blocked_types'] as $blockedType) {
                if (! is_string($blockedType) || ! array_key_exists($blockedType, $objectTypes)) {
                    $this->fail('zones.'.$zoneKey.' references an unknown blocked object type.');
                }
            }
            $this->provenance($zone, $sources, 'zones.'.$zoneKey);
        }

        $structures = $this->requireList($data, 'structures');
        $structureKeys = [];
        foreach ($structures as $index => $structure) {
            if (! is_array($structure)) {
                $this->fail('structures entries must be objects.');
            }
            foreach (['key', 'name', 'category'] as $key) {
                $this->requireString($structure, $key, 'structures.'.$index.'.');
            }
            if (isset($structureKeys[$structure['key']])) {
                $this->fail('structure keys must be unique.');
            }
            $structureKeys[$structure['key']] = true;
            foreach (['x', 'y'] as $key) {
                if (! is_int($structure[$key] ?? null)) {
                    $this->fail('structures.'.$index.'.'.$key.' must be an integer.');
                }
            }
            $this->footprint($structure['footprint'] ?? null, 'structures.'.$index.'.footprint');
            if (! is_int($structure['exclusion_tiles'] ?? null) || $structure['exclusion_tiles'] < 0) {
                $this->fail('structures.'.$index.'.exclusion_tiles must be a non-negative integer.');
            }
            if (! is_bool($structure['city_exempt'] ?? null)) {
                $this->fail('structures.'.$index.'.city_exempt must be boolean.');
            }
            $this->provenance($structure, $sources, 'structures.'.$index);
        }

        $rules = $this->requireList($data, 'placement_rules');
        $ruleKeys = [];
        foreach ($rules as $index => $rule) {
            if (! is_array($rule)) {
                $this->fail('placement_rules entries must be objects.');
            }
            foreach (['key', 'kind', 'statement'] as $key) {
                $this->requireString($rule, $key, 'placement_rules.'.$index.'.');
            }
            if (isset($ruleKeys[$rule['key']])) {
                $this->fail('placement rule keys must be unique.');
            }
            $ruleKeys[$rule['key']] = true;
            if (! in_array($rule['kind'], ['blocking', 'warning', 'advisory', 'fact'], true)) {
                $this->fail('placement_rules.'.$index.'.kind is unsupported.');
            }
            $this->provenance($rule, $sources, 'placement_rules.'.$index);
        }

        $layers = $this->requireMap($data, 'resource_layers');
        $resourceNodes = $this->requireMap($layers, 'resource_nodes', 'resource_layers.');
        $this->nonNegativeCount($resourceNodes['count'] ?? null, 'resource_layers.resource_nodes.count');
        $this->provenance($resourceNodes, $sources, 'resource_layers.resource_nodes');
        $terrain = $this->requireMap($layers, 'terrain', 'resource_layers.');
        $this->nonNegativeCount($terrain['lakes'] ?? null, 'resource_layers.terrain.lakes');
        $this->nonNegativeCount($terrain['mountains'] ?? null, 'resource_layers.terrain.mountains');
        $this->provenance($terrain, $sources, 'resource_layers.terrain');
    }

    /** @param array<string,mixed> $data */
    private function requireString(array $data, string $key, string $prefix = ''): void
    {
        if (! is_string($data[$key] ?? null) || trim($data[$key]) === '') {
            $this->fail($prefix.$key.' must be a non-empty string.');
        }
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function requireMap(array $data, string $key, string $prefix = ''): array
    {
        $value = $data[$key] ?? null;
        if (! is_array($value) || array_is_list($value)) {
            $this->fail($prefix.$key.' must be an object.');
        }

        return $value;
    }

    /** @param array<string,mixed> $data @return list<mixed> */
    private function requireList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (! is_array($value) || ! array_is_list($value)) {
            $this->fail($key.' must be a list.');
        }

        return $value;
    }

    private function confidence(mixed $value, string $path): void
    {
        if (! is_string($value) || MapDatasetConfidence::tryFrom($value) === null) {
            $this->fail($path.' contains an unsupported confidence value.');
        }
    }

    private function footprint(mixed $value, string $path): void
    {
        if (! is_array($value) || array_is_list($value)) {
            $this->fail($path.' must be an object.');
        }
        foreach (['width', 'height'] as $key) {
            if (! is_int($value[$key] ?? null) || $value[$key] < 1) {
                $this->fail($path.'.'.$key.' must be a positive integer.');
            }
        }
    }

    /** @param array<string,mixed> $fact @param array<string,mixed> $sources */
    private function provenance(array $fact, array $sources, string $path): void
    {
        $provenance = $fact['provenance'] ?? null;
        if (! is_array($provenance) || ! array_is_list($provenance) || $provenance === []) {
            $this->fail($path.'.provenance must contain at least one source ID.');
        }
        foreach ($provenance as $sourceId) {
            if (! is_string($sourceId) || ! array_key_exists($sourceId, $sources)) {
                $this->fail($path.'.provenance references an unknown source.');
            }
        }
        if (isset($fact['confidence'])) {
            $this->confidence($fact['confidence'], $path.'.confidence');
            $factConfidence = MapDatasetConfidence::from($fact['confidence']);
            foreach ($provenance as $sourceId) {
                $source = $sources[$sourceId];
                if (! is_array($source)) {
                    continue;
                }
                $ceiling = MapDatasetConfidence::from((string) $source['confidence_ceiling']);
                if ($this->rank($factConfidence) > $this->rank($ceiling)) {
                    $this->fail($path.'.confidence exceeds provenance source confidence ceiling.');
                }
            }
        }
    }

    private function rank(MapDatasetConfidence $confidence): int
    {
        return match ($confidence) {
            MapDatasetConfidence::Unknown => 0,
            MapDatasetConfidence::Disputed => 1,
            MapDatasetConfidence::CommunityObserved => 2,
            MapDatasetConfidence::VerifiedObservation => 3,
            MapDatasetConfidence::Official => 4,
        };
    }

    private function nonNegativeCount(mixed $value, string $path): void
    {
        if (! is_int($value) || $value < 0) {
            $this->fail($path.' must be a non-negative integer.');
        }
    }

    private function fail(string $message): never
    {
        throw new RuntimeException('Kingdom map schema V2 validation failed: '.$message);
    }
}
