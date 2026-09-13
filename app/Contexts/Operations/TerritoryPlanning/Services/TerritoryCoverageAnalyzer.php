<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\GameWorld\KingdomMaps\Services\TerritoryCoverageGeometry;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\Rectangle;

final class TerritoryCoverageAnalyzer
{
    public function __construct(private readonly TerritoryCoverageGeometry $geometry) {}

    /**
     * @param  list<array{key:string,type:string,x:int,y:int,alliance_key:string}>  $objects
     * @return array<string, bool>
     */
    public function byGovernorCity(KingdomMapDataset $dataset, array $objects): array
    {
        $byAlliance = [];
        foreach ($objects as $object) {
            $byAlliance[$object['alliance_key']][] = $object;
        }

        $result = [];
        foreach ($byAlliance as $allianceObjects) {
            $coverage = $this->coverageRectangles($dataset, $allianceObjects);
            foreach ($allianceObjects as $city) {
                if ($city['type'] !== 'governor_city') {
                    continue;
                }
                $target = $this->geometry->footprint($dataset, 'governor_city', $city['x'], $city['y'], $city['rotation'] ?? 0);
                $result[$city['key']] = $target instanceof Rectangle
                    && $this->geometry->meetsRatio($target, $coverage, 1.0);
            }
        }

        return $result;
    }

    /**
     * Evaluate the official Century Games Alliance-resource ownership threshold for one
     * observed/planned resource footprint. This rule is intentionally not reused as a
     * Governor-city rule.
     *
     * @param  list<array{key:string,type:string,x:int,y:int,alliance_key:string}>  $objects
     * @return array{covered_ratio:float,minimum_ratio:float,owned:bool}
     */
    public function allianceResourceOwnership(
        KingdomMapDataset $dataset,
        array $objects,
        string $allianceKey,
        Rectangle $resource,
    ): array {
        $allianceObjects = array_values(array_filter(
            $objects,
            static fn (array $object): bool => $object['alliance_key'] === $allianceKey,
        ));
        $coverage = $this->coverageRectangles($dataset, $allianceObjects);
        $minimum = $this->geometry->officialAllianceResourceMinimumRatio($dataset);
        $covered = $this->geometry->coveredRatio($resource, $coverage);

        return [
            'covered_ratio' => $covered,
            'minimum_ratio' => $minimum,
            'owned' => $covered + 1e-9 >= $minimum,
        ];
    }

    /**
     * @param  list<array{key:string,type:string,x:int,y:int,alliance_key:string}>  $objects
     * @return list<Rectangle>
     */
    private function coverageRectangles(KingdomMapDataset $dataset, array $objects): array
    {
        $coverage = [];
        foreach ($objects as $object) {
            $rectangle = $this->geometry->coverage($dataset, $object['type'], $object['x'], $object['y'], $object['rotation'] ?? 0);
            if ($rectangle instanceof Rectangle) {
                $coverage[] = $rectangle;
            }
        }

        return $coverage;
    }
}
