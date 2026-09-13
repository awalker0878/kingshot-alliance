<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\Rectangle;
use LogicException;
use WeakMap;

/** Exact source-cell collision index, never terrain bounding-box approximations. */
final class KingdomMapSpatialPlacementIndex
{
    /** @var WeakMap<KingdomMapDataset, array<string, array<int, list<array{int,int}>>>> */
    private WeakMap $indexes;

    public function __construct()
    {
        $this->indexes = new WeakMap;
    }

    /** @return list<string> */
    public function intersections(KingdomMapDataset $dataset, Rectangle $footprint): array
    {
        if ($footprint->width <= 0 || $footprint->height <= 0) {
            return [];
        }
        $this->indexes[$dataset] ??= $this->build($dataset);
        $bounds = $dataset->data['bounds'];
        $first = max($footprint->y, (int) $bounds['y']);
        $last = min($footprint->bottom(), (int) $bounds['y'] + (int) $bounds['height']);
        $codes = [];
        foreach ($this->indexes[$dataset] as $code => $rows) {
            for ($y = $first; $y < $last; $y++) {
                $intervals = $rows[$y] ?? [];
                // Each row is a sorted disjoint union: binary-search the first
                // interval ending strictly after the footprint's left edge.
                $low = 0;
                $high = count($intervals);
                while ($low < $high) {
                    $middle = intdiv($low + $high, 2);
                    if ($intervals[$middle][1] <= $footprint->x) {
                        $low = $middle + 1;
                    } else {
                        $high = $middle;
                    }
                }
                if (isset($intervals[$low]) && $intervals[$low][0] < $footprint->right()) {
                    $codes[] = $code;
                    break;
                }
            }
        }

        return $codes;
    }

    /** @param array<int, list<array{int,int}>> $rows */
    private function add(array &$rows, int $x, int $y, int $width): void
    {
        if ($width <= 0) {
            throw new LogicException('Materialized Kingdom map placement span is invalid.');
        }
        $rows[$y][] = [$x, $x + $width];
    }

    /** @return array<string, array<int, list<array{int,int}>>> */
    private function build(KingdomMapDataset $dataset): array
    {
        $result = [];
        foreach (['terrain' => 'terrain_features', 'resources' => 'resource_nodes'] as $layer => $collection) {
            $declaration = $dataset->resourceLayers()[$layer] ?? [];
            if (($declaration['data_state'] ?? null) !== 'materialized' || ($declaration['placement_blocking'] ?? null) !== true) {
                continue;
            }
            $features = $dataset->data[$collection] ?? null;
            if (! is_array($features) || ! array_is_list($features)) {
                throw new LogicException('Materialized Kingdom map placement geometry has not been hydrated.');
            }
            /** @var array<int, list<array{int,int}>> $rows */
            $rows = [];
            foreach ($features as $feature) {
                if ($layer === 'terrain') {
                    foreach ($feature['spans'] as [$x, $y, $width]) {
                        $this->add($rows, $x, $y, $width);
                    }
                } else {
                    for ($y = $feature['y']; $y < $feature['y'] + $feature['footprint']['height']; $y++) {
                        $this->add($rows, $feature['x'], $y, $feature['footprint']['width']);
                    }
                }
            }
            foreach ($rows as $y => $intervals) {
                usort($intervals, static fn (array $a, array $b): int => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
                /** @var list<array{int,int}> $merged */
                $merged = [];
                foreach ($intervals as [$start, $end]) {
                    $last = array_key_last($merged);
                    if ($last !== null && $start <= $merged[$last][1]) {
                        $merged[$last] = [$merged[$last][0], max($end, $merged[$last][1])];
                    } else {
                        $merged[] = [$start, $end];
                    }
                }
                $rows[$y] = $merged;
            }
            $result[$layer === 'terrain' ? 'terrain_collision' : 'resource_collision'] = $rows;
        }

        return $result;
    }
}
