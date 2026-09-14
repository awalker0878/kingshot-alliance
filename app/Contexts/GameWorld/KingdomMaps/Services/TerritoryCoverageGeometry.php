<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\Rectangle;
use InvalidArgumentException;

final class TerritoryCoverageGeometry
{
    public function footprint(KingdomMapDataset $dataset, string $type, int $x, int $y, int $rotation = 0): ?Rectangle
    {
        $footprint = $dataset->footprint($type);
        if ($footprint === null) {
            return null;
        }

        [$width, $height] = $this->dimensions($footprint, $rotation);

        return new Rectangle($x, $y, $width, $height);
    }

    public function coverage(KingdomMapDataset $dataset, string $type, int $x, int $y, int $rotation = 0): ?Rectangle
    {
        $footprint = $dataset->footprint($type);
        $coverage = $dataset->coverage($type);
        if ($footprint === null || $coverage === null) {
            return null;
        }

        [$width, $height] = $this->dimensions($coverage, $rotation);
        [$footprintWidth, $footprintHeight] = $this->dimensions($footprint, $rotation);
        $offsetX = intdiv($width - $footprintWidth, 2);
        $offsetY = intdiv($height - $footprintHeight, 2);

        return new Rectangle(
            $x - $offsetX,
            $y - $offsetY,
            $width,
            $height,
        );
    }

    /** @param array{width:int,height:int} $size
     * @return array{int,int}
     */
    private function dimensions(array $size, int $rotation): array
    {
        if (! in_array($rotation, [0, 90, 180, 270], true)) {
            throw new InvalidArgumentException('Rotation must be 0, 90, 180, or 270 degrees.');
        }

        return in_array($rotation, [90, 270], true)
            ? [$size['height'], $size['width']]
            : [$size['width'], $size['height']];
    }

    /** Exact rectangle union area; bounded by rectangle count rather than world dimensions.
     * @param  list<Rectangle>  $rectangles
     */
    public function unionArea(array $rectangles): int
    {
        $edges = [];
        foreach ($rectangles as $rect) {
            $edges[] = $rect->x;
            $edges[] = $rect->right();
        }
        $edges = array_values(array_unique($edges));
        sort($edges, SORT_NUMERIC);
        $area = 0;
        for ($i = 1, $count = count($edges); $i < $count; $i++) {
            $intervals = [];
            foreach ($rectangles as $rect) {
                if ($rect->x < $edges[$i] && $rect->right() > $edges[$i - 1]) {
                    $intervals[] = [$rect->y, $rect->bottom()];
                }
            }
            usort($intervals, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
            $length = 0;
            $end = null;
            foreach ($intervals as [$start, $stop]) {
                $length += max(0, $stop - max($start, $end ?? $start));
                $end = max($end ?? $stop, $stop);
            }
            $area += ($edges[$i] - $edges[$i - 1]) * $length;
        }

        return $area;
    }

    /** @param list<Rectangle> $territory */
    public function coveredRatio(Rectangle $target, array $territory): float
    {
        if ($target->area() < 1) {
            return 0.0;
        }

        $covered = 0;
        for ($x = $target->x; $x < $target->right(); $x++) {
            for ($y = $target->y; $y < $target->bottom(); $y++) {
                foreach ($territory as $coverage) {
                    if ($coverage->containsCell($x, $y)) {
                        $covered++;
                        break;
                    }
                }
            }
        }

        return $covered / $target->area();
    }

    /** @param list<Rectangle> $territory */
    public function meetsRatio(Rectangle $target, array $territory, float $minimumRatio): bool
    {
        if ($minimumRatio < 0.0 || $minimumRatio > 1.0) {
            throw new InvalidArgumentException('Coverage minimum ratio must be between 0 and 1.');
        }

        return $this->coveredRatio($target, $territory) + 1e-9 >= $minimumRatio;
    }

    public function officialAllianceResourceMinimumRatio(KingdomMapDataset $dataset): float
    {
        foreach ($dataset->data['placement_rules'] ?? [] as $rule) {
            if (! is_array($rule) || ($rule['key'] ?? null) !== 'alliance_resource_territory_ratio') {
                continue;
            }
            $parameters = $rule['parameters'] ?? null;
            $ratio = is_array($parameters) ? ($parameters['minimum_covered_ratio'] ?? null) : null;
            if (is_float($ratio) || is_int($ratio)) {
                return (float) $ratio;
            }
        }

        throw new InvalidArgumentException('Selected Kingdom map release has no Alliance resource territory ratio rule.');
    }

    /**
     * @param  list<Rectangle>  $coverage
     * @return list<list<int>>
     */
    public function components(array $coverage): array
    {
        $visited = [];
        $components = [];
        foreach (array_keys($coverage) as $start) {
            if (isset($visited[$start])) {
                continue;
            }
            $component = [];
            $queue = [$start];
            while ($queue !== []) {
                $index = array_pop($queue);
                if ($index === null || isset($visited[$index])) {
                    continue;
                }
                $visited[$index] = true;
                $component[] = $index;
                foreach ($coverage as $candidateIndex => $candidate) {
                    if (isset($visited[$candidateIndex]) || $candidateIndex === $index) {
                        continue;
                    }
                    if ($coverage[$index]->touchesOrIntersects($candidate)) {
                        $queue[] = $candidateIndex;
                    }
                }
            }
            sort($component);
            $components[] = $component;
        }

        return $components;
    }

    /** @param list<Rectangle> $coverage */
    public function componentCount(array $coverage): int
    {
        return count($this->components($coverage));
    }
}
