<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;

final class TerritoryCoverageAnalyzer
{
    /**
     * @param list<array{key:string,type:string,x:int,y:int,alliance_key:string}> $objects
     * @return array<string,bool>
     */
    public function byGovernorCity(KingdomMapDataset $dataset, array $objects): array
    {
        $byAlliance = [];
        foreach ($objects as $object) {
            $byAlliance[$object['alliance_key']][] = $object;
        }
        $result = [];
        foreach ($byAlliance as $allianceObjects) {
            $sources = [];
            $cities = [];
            foreach ($allianceObjects as $object) {
                $definition = is_array($dataset->data['object_types'][$object['type']] ?? null)
                    ? $dataset->data['object_types'][$object['type']]
                    : [];
                $coverage = (float) ($definition['coverage'] ?? 0);
                $size = (float) ($definition['size'] ?? 1);
                if ($coverage > 0) {
                    $sources[] = [
                        'x' => $object['x'] + ($size / 2),
                        'y' => $object['y'] + ($size / 2),
                        'coverage' => $coverage,
                    ];
                }
                if ($object['type'] === 'governor_city') {
                    $cities[] = $object;
                }
            }
            $citySize = (float) (($dataset->data['object_types']['governor_city']['size'] ?? 2));
            foreach ($cities as $city) {
                $covered = true;
                foreach ([
                    [$city['x'], $city['y']],
                    [$city['x'] + $citySize, $city['y']],
                    [$city['x'], $city['y'] + $citySize],
                    [$city['x'] + $citySize, $city['y'] + $citySize],
                ] as [$x, $y]) {
                    if (! $this->pointCovered((float) $x, (float) $y, $sources)) {
                        $covered = false;
                        break;
                    }
                }
                $result[$city['key']] = $covered;
            }
        }
        return $result;
    }

    /** @param list<array{x:float,y:float,coverage:float}> $sources */
    private function pointCovered(float $x, float $y, array $sources): bool
    {
        foreach ($sources as $source) {
            if (abs($x - $source['x']) <= $source['coverage'] && abs($y - $source['y']) <= $source['coverage']) {
                return true;
            }
        }
        return false;
    }
}
