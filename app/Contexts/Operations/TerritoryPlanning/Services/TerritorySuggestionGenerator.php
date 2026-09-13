<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;

/** Officer-review alternatives; accepted mutations remain the plan owner's responsibility. */
final readonly class TerritorySuggestionGenerator
{
    public function __construct(private HiveLayoutGenerator $hives, private TerritoryLayoutAnalyzer $analysis) {}

    /**
     * @param  list<array{key:string,type:string,x:int,y:int,alliance_key:string,rotation?:int}>  $existingObjects
     * @param  array<string,mixed>  $preferences
     * @return array<string,mixed>
     */
    public function compare(KingdomMapDataset $dataset, array $existingObjects, string $allianceKey, int $centerX, int $centerY, int $cityCount = 50, int $spacing = 1, array $preferences = []): array
    {
        $candidates = [];
        foreach (['swirl', 'banner_pad'] as $style) {
            $candidate = $this->hives->preview($dataset, $existingObjects, $style, $allianceKey, $centerX, $centerY, $cityCount, $spacing, $preferences);
            $candidate['style'] = $style;
            if ($candidate['status'] === 'feasible') {
                $candidate['analysis'] = $this->analysis->analyze($dataset, array_merge($existingObjects, $candidate['objects']), $preferences);
                $metrics = $candidate['analysis']['alliances'][$allianceKey];
                $radius = $preferences['preferred_bear_radius_tiles'] ?? null;
                $average = $metrics['bear_distance_tiles']['average'];
                $bannerArea = ($dataset->coverage('banner')['width'] ?? 0) * ($dataset->coverage('banner')['height'] ?? 0) * ($metrics['counts']['banner'] ?? 0);
                $components = [
                    'coverage' => $metrics['coverage_percent'],
                    'banner_efficiency' => $bannerArea > 0 ? round(100 * $metrics['useful_banner_area_tiles'] / $bannerArea, 2) : null,
                    'distance' => is_numeric($radius) && (float) $radius > 0 && $average !== null ? round(100 * (1 - min(1, $average / (float) $radius)), 2) : null,
                    'density' => $metrics['hive_density_percent'],
                ];
                $weights = ['coverage' => 0.4, 'banner_efficiency' => 0.25, 'distance' => 0.2, 'density' => 0.15];
                $missing = array_keys(array_filter($components, static fn ($value): bool => $value === null));
                $score = 0.0;
                foreach ($components as $component => $value) {
                    $score += ($value ?? 0) * $weights[$component];
                }
                $candidate['score'] = [
                    'state' => $missing === [] ? 'available' : 'unavailable',
                    'value' => $missing === [] ? round($score, 2) : null,
                    'components' => $components,
                    'weights' => $weights,
                    'missing_components' => $missing,
                    'distance_normalization_tiles' => $radius,
                    'preset' => 'officer-planning-v1',
                    'explanation' => 'Coverage uses fully covered city footprints; Banner efficiency uses additional union area divided by summed Banner area; distance declines linearly to zero at the preferred radius; density uses city footprint union divided by enclosing bounds. Weights are planning preferences, not game rules. Missing components are never silently reweighted.',
                ];
            }
            $candidates[] = $candidate;
        }

        return ['algorithm_version' => HiveLayoutGenerator::ALGORITHM_VERSION, 'candidates' => $candidates];
    }
}
