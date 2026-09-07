<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Services;

use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\Rectangle;
use InvalidArgumentException;

final class TerritoryCoverageGeometry
{
    public function footprint(KingdomMapDataset $dataset, string $type, int $x, int $y): ?Rectangle
    {
        $footprint = $dataset->footprint($type);
        if ($footprint === null) {
            return null;
        }

        return new Rectangle($x, $y, $footprint['width'], $footprint['height']);
    }

    public function coverage(KingdomMapDataset $dataset, string $type, int $x, int $y): ?Rectangle
    {
        $footprint = $dataset->footprint($type);
        $coverage = $dataset->coverage($type);
        if ($footprint === null || $coverage === null) {
            return null;
        }

        $offsetX = intdiv($coverage['width'] - $footprint['width'], 2);
        $offsetY = intdiv($coverage['height'] - $footprint['height'], 2);

        return new Rectangle(
            $x - $offsetX,
            $y - $offsetY,
            $coverage['width'],
            $coverage['height'],
        );
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
