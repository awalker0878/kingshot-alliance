<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\PlacementValidationResult;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\Rectangle;

final class PlacementValidator
{
    /**
     * @param  list<array{key: string, type: string, x: int, y: int, alliance_key: string}>  $objects
     * @param  array<string, mixed>  $preferences
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
        $countsByAlliance = [];

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
            $countKey = $object['alliance_key'].'|'.$object['type'];
            $countsByAlliance[$countKey] = ($countsByAlliance[$countKey] ?? 0) + 1;
            $maximum = $definition['max_per_alliance'] ?? null;
            if (is_int($maximum) && $maximum > 0 && $countsByAlliance[$countKey] > $maximum) {
                $violations[] = $this->issue(
                    'alliance_object_cap',
                    'This Alliance exceeds the selected map dataset object cap.',
                    $object['key'],
                );
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
                $structureSize = (int) ($structure['size'] ?? 0);
                $structureRect = new Rectangle((int) $structure['x'], (int) $structure['y'], $structureSize, $structureSize);
                if ($rect->intersects($structureRect)) {
                    $violations[] = $this->issue('structure_collision', 'The object overlaps a fixed Kingdom structure.', $object['key']);
                    break;
                }
                $exclusion = max((int) ($structure['exclusion'] ?? 0), 0);
                if ($exclusion === 0) {
                    continue;
                }
                $forbidden = new Rectangle((int) $structure['x'] - $exclusion, (int) $structure['y'] - $exclusion, $structureSize + ($exclusion * 2), $structureSize + ($exclusion * 2));
                $cityExempt = (bool) ($structure['city_exempt'] ?? false);
                if ($rect->intersects($forbidden) && ! ($object['type'] === 'governor_city' && $cityExempt)) {
                    $violations[] = $this->issue('structure_exclusion', 'The object overlaps a fixed structure no-build zone.', $object['key']);
                    break;
                }
            }

            foreach ($data['zones'] as $zone) {
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
            $trapsByAlliance = [];
            foreach ($objects as $object) {
                if ($object['type'] === 'bear_trap') {
                    $trapsByAlliance[$object['alliance_key']][] = $object;
                }
            }
            $selectedBearTraps = is_array($preferences['selected_bear_trap_by_alliance'] ?? null)
                ? $preferences['selected_bear_trap_by_alliance']
                : [];
            foreach ($objects as $object) {
                if ($object['type'] !== 'governor_city') {
                    continue;
                }
                $traps = $trapsByAlliance[$object['alliance_key']] ?? [];
                if ($traps === []) {
                    continue;
                }
                $selectedKey = is_string($selectedBearTraps[$object['alliance_key']] ?? null)
                    ? $selectedBearTraps[$object['alliance_key']]
                    : null;
                $targetTrap = $traps[0];
                $targetDistance = hypot($object['x'] - $targetTrap['x'], $object['y'] - $targetTrap['y']);
                if ($selectedKey !== null) {
                    foreach ($traps as $trap) {
                        if ($trap['key'] === $selectedKey) {
                            $targetTrap = $trap;
                            $targetDistance = hypot($object['x'] - $trap['x'], $object['y'] - $trap['y']);
                            break;
                        }
                    }
                } else {
                    foreach (array_slice($traps, 1) as $trap) {
                        $distance = hypot($object['x'] - $trap['x'], $object['y'] - $trap['y']);
                        if ($distance < $targetDistance) {
                            $targetDistance = $distance;
                            $targetTrap = $trap;
                        }
                    }
                }
                if ($targetDistance > (float) $targetRadius) {
                    $warnings[] = $this->issue('preferred_bear_radius', 'This Governor city is outside the plan preferred Bear Trap radius.', $object['key']);
                }
            }
        }

        $violatingObjectKeys = [];
        foreach ($violations as $violation) {
            if (isset($violation['object_key'])) {
                $violatingObjectKeys[$violation['object_key']] = true;
            }
        }
        $objectsByAlliance = [];
        foreach ($objects as $object) {
            $objectsByAlliance[$object['alliance_key']][] = $object;
        }
        foreach ($objectsByAlliance as $scopedObjects) {
            $hasBlockingViolation = false;
            foreach ($scopedObjects as $object) {
                if (isset($violatingObjectKeys[$object['key']])) {
                    $hasBlockingViolation = true;
                    break;
                }
            }
            if ($hasBlockingViolation) {
                continue;
            }
            $firstCity = null;
            $hasHeadquarters = false;
            $hasBanner = false;
            $hasBearTrap = false;
            foreach ($scopedObjects as $object) {
                $firstCity ??= $object['type'] === 'governor_city' ? $object['key'] : null;
                $hasHeadquarters = $hasHeadquarters || $object['type'] === 'headquarters';
                $hasBanner = $hasBanner || $object['type'] === 'banner';
                $hasBearTrap = $hasBearTrap || $object['type'] === 'bear_trap';
            }
            if (! is_string($firstCity)) {
                continue;
            }
            if (! $hasHeadquarters) {
                $suggestions[] = $this->issue('consider_headquarters', 'Consider placing the Alliance HQ before finalizing this layout.', $firstCity);
            }
            if (! $hasBanner) {
                $suggestions[] = $this->issue('consider_banner_coverage', 'Consider adding Alliance Banners to establish territory coverage for Governor cities.', $firstCity);
            }
            if (! $hasBearTrap) {
                $suggestions[] = $this->issue('consider_bear_trap', 'Consider placing a Bear Trap to analyze hive march distances.', $firstCity);
            }
        }

        return new PlacementValidationResult($this->unique($violations), $this->unique($warnings), $this->unique($suggestions));
    }

    /** @return array{code: string, message: string, object_key: string} */
    private function issue(string $code, string $message, string $objectKey): array
    {
        return ['code' => $code, 'message' => $message, 'object_key' => $objectKey];
    }

    /**
     * @param  list<array{code: string, message: string, object_key?: string}>  $issues
     * @return list<array{code: string, message: string, object_key?: string}>
     */
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
