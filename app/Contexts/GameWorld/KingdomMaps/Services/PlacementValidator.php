<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\PlacementValidationResult;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\Rectangle;

final class PlacementValidator
{
    /**
     * @param list<array{key:string,type:string,x:int,y:int,alliance_key:string}> $objects
     * @param array<string,mixed> $preferences
     */
    public function validate(KingdomMapDataset $dataset, array $objects, array $preferences = []): PlacementValidationResult
    {
        $violations = [];
        $warnings = [];
        $suggestions = [];
        $data = $dataset->data;
        $boundsData = $data['bounds'];
        $bounds = new Rectangle((int) $boundsData['x'], (int) $boundsData['y'], (int) $boundsData['width'], (int) $boundsData['height']);
        $rectangles = [];

        foreach ($objects as $object) {
            $definition = $data['object_types'][$object['type']] ?? null;
            if (! is_array($definition)) {
                $violations[] = $this->issue('unknown_object_type', 'This object type is not supported by the selected map dataset.', $object['key']);
                continue;
            }

            $size = (int) ($definition['size'] ?? 0);
            if ($size < 1) {
                $violations[] = $this->issue('invalid_object_footprint', 'The selected map dataset has no valid footprint for this object.', $object['key']);
                continue;
            }

            $rect = new Rectangle($object['x'], $object['y'], $size, $size);
            $rectangles[$object['key']] = $rect;
            if (! $rect->inside($bounds)) {
                $violations[] = $this->issue('map_bounds', 'The object footprint must stay inside the Kingdom map.', $object['key']);
                continue;
            }

            foreach ($data['structures'] as $structure) {
                if (! is_array($structure)) {
                    continue;
                }
                $exclusion = max((int) ($structure['exclusion'] ?? 0), 0);
                $structureRect = new Rectangle(
                    (int) $structure['x'] - $exclusion,
                    (int) $structure['y'] - $exclusion,
                    (int) $structure['size'] + ($exclusion * 2),
                    (int) $structure['size'] + ($exclusion * 2),
                );
                $cityExempt = (bool) ($structure['city_exempt'] ?? false);
                if ($rect->intersects($structureRect) && ! ($object['type'] === 'governor_city' && $cityExempt)) {
                    $violations[] = $this->issue('structure_exclusion', 'The object overlaps a fixed structure or its no-build zone.', $object['key']);
                    break;
                }
            }

            foreach ($data['zones'] as $zoneKey => $zone) {
                if (! is_array($zone)) {
                    continue;
                }
                $zoneRect = new Rectangle((int) $zone['x'], (int) $zone['y'], (int) $zone['width'], (int) $zone['height']);
                if (! $rect->intersects($zoneRect)) {
                    continue;
                }
                $blockedTypes = is_array($zone['blocked_types'] ?? null) ? $zone['blocked_types'] : [];
                if (in_array($object['type'], $blockedTypes, true)) {
                    $violations[] = $this->issue('zone_restriction', 'The object type is not allowed in this map zone.', $object['key']);
                }
            }
        }

        $keys = array_keys($rectangles);
        for ($i = 0, $count = count($keys); $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($rectangles[$keys[$i]]->intersects($rectangles[$keys[$j]])) {
                    $violations[] = $this->issue('object_collision', 'Planned object footprints cannot overlap.', $keys[$j]);
                }
            }
        }

        $targetRadius = $preferences['preferred_bear_radius_tiles'] ?? null;
        if (is_numeric($targetRadius) && (float) $targetRadius > 0) {
            $trapByAlliance = [];
            foreach ($objects as $object) {
                if ($object['type'] === 'bear_trap') {
                    $trapByAlliance[$object['alliance_key']][] = $object;
                }
            }
            foreach ($objects as $object) {
                if ($object['type'] !== 'governor_city' || ! isset($trapByAlliance[$object['alliance_key']][0])) {
                    continue;
                }
                $trap = $trapByAlliance[$object['alliance_key']][0];
                $distance = hypot($object['x'] - $trap['x'], $object['y'] - $trap['y']);
                if ($distance > (float) $targetRadius) {
                    $warnings[] = $this->issue('preferred_bear_radius', 'This Governor city is outside the plan preferred Bear Trap radius.', $object['key']);
                }
            }
        }

        return new PlacementValidationResult($this->unique($violations), $this->unique($warnings), $suggestions);
    }

    /** @return array{code:string,message:string,object_key:string} */
    private function issue(string $code, string $message, string $objectKey): array
    {
        return ['code' => $code, 'message' => $message, 'object_key' => $objectKey];
    }

    /** @param list<array{code:string,message:string,object_key?:string}> $issues @return list<array{code:string,message:string,object_key?:string}> */
    private function unique(array $issues): array
    {
        $seen = [];
        $result = [];
        foreach ($issues as $issue) {
            $key = $issue['code'].'|'.($issue['object_key'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $issue;
        }

        return $result;
    }
}
