<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\GameWorld\KingdomMaps\Services\PlacementValidator;
use App\Contexts\GameWorld\KingdomMaps\Services\TerritoryCoverageGeometry;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use App\Contexts\GameWorld\KingdomMaps\ValueObjects\Rectangle;
use Illuminate\Validation\ValidationException;

/** Deterministic, bounded proposal generation. This service never saves a layout. */
final readonly class HiveLayoutGenerator
{
    public const ALGORITHM_VERSION = 'hive-grid-v2';

    public function __construct(
        private PlacementValidator $placement,
        private TerritoryCoverageGeometry $geometry,
    ) {}

    /**
     * @param  list<array{key:string,type:string,x:int,y:int,alliance_key:string,rotation?:int}>  $existingObjects
     * @param  array<string,mixed>  $preferences
     * @return array{status:string,algorithm_version:string,requested_city_count:int,generated_city_count:int,objects:list<array{key:string,type:string,x:int,y:int,alliance_key:string,rotation?:int}>,diagnostics:list<array{code:string,message:string}>,map_dataset_id:string,map_dataset_checksum:string,input_checksum:string,assumptions:array<string,string|int>}
     */
    public function preview(
        KingdomMapDataset $dataset,
        array $existingObjects,
        string $style,
        string $allianceKey,
        int $centerX,
        int $centerY,
        int $cityCount = 50,
        int $spacing = 1,
        array $preferences = [],
    ): array {
        if (! in_array($style, ['swirl', 'banner_pad'], true)) {
            throw ValidationException::withMessages(['style' => 'Hive style must be swirl or banner_pad.']);
        }
        if ($cityCount < 1 || $cityCount > 100 || $spacing < 0 || $spacing > 10 || count($existingObjects) > 5000) {
            throw ValidationException::withMessages(['city_count' => 'Choose 1–100 cities, 0–10 spacing tiles, and at most 5000 existing objects.']);
        }
        $input = [$dataset->id, $dataset->checksum, $existingObjects, $style, $allianceKey, $centerX, $centerY, $cityCount, $spacing, $preferences];
        $checksum = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        $group = 'hive-'.substr($checksum, 0, 16);
        $base = [
            'status' => 'infeasible',
            'algorithm_version' => self::ALGORITHM_VERSION,
            'requested_city_count' => $cityCount,
            'generated_city_count' => 0,
            'objects' => [],
            'diagnostics' => [],
            'map_dataset_id' => $dataset->id,
            'map_dataset_checksum' => $dataset->checksum,
            'input_checksum' => $checksum,
            'assumptions' => ['city_coverage' => 'entire_footprint', 'spacing_tiles' => $spacing, 'search' => 'bounded_greedy_not_global_optimum'],
        ];
        $fail = static function (string $code, string $message, int $generated = 0) use ($base): array {
            return array_replace($base, ['generated_city_count' => $generated, 'diagnostics' => [['code' => $code, 'message' => $message]]]);
        };
        if (! $this->placement->validate($dataset, $existingObjects, $preferences)->valid()) {
            return $fail('existing_layout_invalid', 'Resolve existing placement violations before generating a hive.');
        }
        $scoped = array_values(array_filter($existingObjects, static fn (array $object): bool => $object['alliance_key'] === $allianceKey));
        $headquarters = array_values(array_filter($scoped, static fn (array $object): bool => $object['type'] === 'headquarters'));
        if ($headquarters === []) {
            return $fail('headquarters_required', 'Place the selected Alliance Headquarters before generating connected hive alternatives.');
        }
        $counts = array_count_values(array_column($scoped, 'type'));
        $maximumCities = $dataset->objectDefinition('governor_city')['max_per_alliance'] ?? 100;
        if (($counts['governor_city'] ?? 0) + $cityCount > $maximumCities) {
            return $fail('city_cap', 'The requested new cities exceed the selected release Alliance city cap.');
        }
        $rectangles = [];
        $cityRectangles = [];
        $keys = [];
        foreach ($existingObjects as $object) {
            $rect = $this->geometry->footprint($dataset, $object['type'], $object['x'], $object['y'], $object['rotation'] ?? 0);
            if ($rect instanceof Rectangle) {
                $rectangles[] = $rect;
                if ($object['type'] === 'governor_city') {
                    $cityRectangles[] = $rect;
                }
            }
            $keys[$object['key']] = true;
        }
        $coverage = [];
        foreach ($scoped as $object) {
            $rect = $this->geometry->coverage($dataset, $object['type'], $object['x'], $object['y'], $object['rotation'] ?? 0);
            if ($rect instanceof Rectangle) {
                $coverage[] = $rect;
            }
        }
        $make = static fn (string $type, int $x, int $y, string $suffix): array => [
            'key' => $group.'-'.$suffix, 'type' => $type, 'x' => $x, 'y' => $y,
            'alliance_key' => $allianceKey, 'group_key' => $group, 'rotation' => 0,
            'player_id' => null, 'external_player_name' => null, 'label' => null,
            'sort_order' => 0, 'metadata' => $type === 'governor_city' ? ['slot_state' => 'open'] : [],
        ];
        $additions = [];
        $selected = $preferences['selected_bear_trap_by_alliance'][$allianceKey] ?? null;
        $trap = null;
        foreach ($scoped as $object) {
            if ($object['type'] === 'bear_trap' && ($object['key'] === $selected || ($selected === null && $object['x'] === $centerX && $object['y'] === $centerY))) {
                $trap = $object;
                $centerX = $object['x'];
                $centerY = $object['y'];
                break;
            }
        }
        if ($selected !== null && $trap === null) {
            return $fail('selected_bear_missing', 'The selected Bear Trap is absent from this Alliance layout.');
        }
        if ($trap === null) {
            if (($counts['bear_trap'] ?? 0) >= ($dataset->objectDefinition('bear_trap')['max_per_alliance'] ?? 2)) {
                return $fail('bear_cap', 'Select an existing Bear Trap or free a Bear Trap slot first.');
            }
            $trap = $make('bear_trap', $centerX, $centerY, 'trap');
            if (! $this->canPlace($dataset, $trap, $rectangles, $keys)) {
                return $fail('bear_location_blocked', 'The requested Bear Trap footprint overlaps an object, fixed obstacle, restricted area, or map edge.');
            }
            $additions[] = $trap;
            $trapRect = $this->geometry->footprint($dataset, 'bear_trap', $centerX, $centerY);
            if ($trapRect instanceof Rectangle) {
                $rectangles[] = $trapRect;
            }
        }
        $bannerSize = $dataset->coverage('banner');
        if ($bannerSize === null) {
            return $fail('banner_coverage_unavailable', 'This release has no sourced Banner coverage geometry.');
        }
        // Add support Banners outward from the requested hive. Every new coverage
        // rectangle must touch the already HQ-connected network.
        $bannerBudget = min(48, max(0, ($dataset->objectDefinition('banner')['max_per_alliance'] ?? 0) - ($counts['banner'] ?? 0)), max(8, (int) ceil($cityCount / 2)));
        $bannerStep = max(1, min($bannerSize['width'], $bannerSize['height']) - 1);
        $bannerCoordinates = $this->coordinates($centerX, $centerY, $bannerStep, $bannerStep, 10, $style);
        $bannerNumber = 0;
        for ($pass = 0; $pass < 4 && $bannerNumber < $bannerBudget; $pass++) {
            foreach ($bannerCoordinates as [$x, $y]) {
                if ($bannerNumber >= $bannerBudget) {
                    break;
                }
                $candidate = $make('banner', $x, $y, 'banner-'.($bannerNumber + 1));
                $candidateCoverage = $this->geometry->coverage($dataset, 'banner', $x, $y);
                if (! $candidateCoverage instanceof Rectangle || ! $this->touchesNetwork($candidateCoverage, $coverage)
                    || $this->geometry->coveredRatio($candidateCoverage, $coverage) >= 1.0
                    || ! $this->canPlace($dataset, $candidate, $rectangles, $keys)) {
                    continue;
                }
                $additions[] = $candidate;
                $bannerRect = $this->geometry->footprint($dataset, 'banner', $x, $y);
                if ($bannerRect instanceof Rectangle) {
                    $rectangles[] = $bannerRect;
                }
                $coverage[] = $candidateCoverage;
                $bannerNumber++;
            }
        }
        $citySize = $dataset->footprint('governor_city');
        if ($citySize === null) {
            return $fail('city_geometry_unavailable', 'This release has no sourced Governor city footprint.');
        }
        $cityNumber = 0;
        foreach ($this->coordinates($centerX, $centerY, $citySize['width'] + $spacing, $citySize['height'] + $spacing, 32, $style) as [$x, $y]) {
            if ($cityNumber === $cityCount) {
                break;
            }
            $candidate = $make('governor_city', $x, $y, 'city-'.($cityNumber + 1));
            $rect = $this->geometry->footprint($dataset, 'governor_city', $x, $y);
            if (! $rect instanceof Rectangle || ! $this->geometry->meetsRatio($rect, $coverage, 1.0)
                || ! $this->canPlace($dataset, $candidate, $rectangles, $keys)) {
                continue;
            }
            $spacingRect = new Rectangle($x - $spacing, $y - $spacing, $rect->width + 2 * $spacing, $rect->height + 2 * $spacing);
            if ($this->intersectsAny($spacingRect, $cityRectangles)) {
                continue;
            }
            $additions[] = $candidate;
            $rectangles[] = $rect;
            $cityRectangles[] = $rect;
            $cityNumber++;
        }
        if ($cityNumber !== $cityCount) {
            return $fail('bounded_search_shortfall', 'No complete candidate was found within the bounded search. Try fewer cities, less spacing, or a different center; no partial layout has been accepted.', $cityNumber);
        }
        $complete = array_merge($existingObjects, $additions);
        if (count($complete) > 5000 || ! $this->placement->validate($dataset, $complete, $preferences)->valid()) {
            return $fail('final_validation_failed', 'The complete proposal did not pass the selected release placement and layout limits.');
        }

        return array_replace($base, ['status' => 'feasible', 'generated_city_count' => $cityNumber, 'objects' => $additions]);
    }

    /** @param list<Rectangle> $rectangles
     * @param  array<string,bool>  $keys
     * @param  array{key:string,type:string,x:int,y:int,alliance_key:string,rotation?:int}  $object
     */
    private function canPlace(KingdomMapDataset $dataset, array $object, array $rectangles, array $keys): bool
    {
        if (isset($keys[$object['key']])) {
            return false;
        }
        $rect = $this->geometry->footprint($dataset, $object['type'], $object['x'], $object['y']);
        if (! $rect instanceof Rectangle || $this->intersectsAny($rect, $rectangles)) {
            return false;
        }
        foreach ($this->placement->validate($dataset, [$object])->violations as $issue) {
            if ($issue['code'] !== 'banner_hq_connectivity') {
                return false;
            }
        }

        return true;
    }

    /** @param list<Rectangle> $rectangles */
    private function intersectsAny(Rectangle $target, array $rectangles): bool
    {
        foreach ($rectangles as $rect) {
            if ($target->intersects($rect)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Rectangle> $coverage */
    private function touchesNetwork(Rectangle $target, array $coverage): bool
    {
        foreach ($coverage as $rect) {
            if ($target->touchesOrIntersects($rect)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{int,int}> */
    private function coordinates(int $centerX, int $centerY, int $stepX, int $stepY, int $rings, string $style): array
    {
        $result = [];
        for ($y = -$rings; $y <= $rings; $y++) {
            for ($x = -$rings; $x <= $rings; $x++) {
                $result[] = [$centerX + $x * $stepX, $centerY + $y * $stepY];
            }
        }
        usort($result, static function (array $a, array $b) use ($centerX, $centerY, $style): int {
            $distanceA = $style === 'swirl' ? hypot($a[0] - $centerX, $a[1] - $centerY) : max(abs($a[0] - $centerX), abs($a[1] - $centerY));
            $distanceB = $style === 'swirl' ? hypot($b[0] - $centerX, $b[1] - $centerY) : max(abs($b[0] - $centerX), abs($b[1] - $centerY));

            return ($distanceA <=> $distanceB) ?: (($a[1] <=> $b[1]) ?: ($a[0] <=> $b[0]));
        });

        return $result;
    }
}
