<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\v3\TestCase;

final class ProgressionPlannerArchitectureV3Test extends TestCase
{
    public function test_progression_calculators_remain_inside_gameworld_progression(): void
    {
        self::assertDirectoryDoesNotExist(base_path('app/Contexts/Calculators'));
        self::assertDirectoryDoesNotExist(base_path('app/Contexts/Calculator'));
        self::assertFileExists(base_path('app/Contexts/GameWorld/Progression/Services/ProgressionCalculator.php'));
        self::assertFileExists(base_path('app/Contexts/GameWorld/Progression/Queries/CalculatorEligibilityQuery.php'));
    }

    public function test_progression_planner_is_read_only_cross_owner_composition(): void
    {
        $sources = $this->phpSources(base_path('app/ReadModels/Progression'));
        self::assertNotEmpty($sources);

        foreach ([
            ' extends Model',
            '\\Actions\\',
            '->save(',
            '->delete(',
            '->update(',
            '::create(',
            '::insert(',
            '::upsert(',
            'DB::statement(',
            'DB::insert(',
            'DB::update(',
            'DB::delete(',
        ] as $needle) {
            foreach ($sources as $path => $source) {
                self::assertStringNotContainsString(
                    $needle,
                    $source,
                    $path.' must remain a read-only Progression composition; forbidden token: '.$needle,
                );
            }
        }
    }

    public function test_owner_contexts_do_not_depend_on_progression_read_model(): void
    {
        foreach ($this->phpSources(base_path('app/Contexts')) as $path => $source) {
            self::assertStringNotContainsString(
                'App\\ReadModels\\Progression',
                $source,
                $path.' must not import the Progression read model.',
            );
        }
    }

    public function test_gameworld_progression_does_not_import_alliance_intelligence_or_read_models(): void
    {
        foreach ($this->phpSources(base_path('app/Contexts/GameWorld/Progression')) as $path => $source) {
            foreach ([
                'App\\Contexts\\Alliance',
                'App\\Contexts\\Intelligence',
                'App\\ReadModels',
            ] as $forbidden) {
                self::assertStringNotContainsString(
                    $forbidden,
                    $source,
                    $path.' must own factual progression/calculation truth without importing '.$forbidden.'.',
                );
            }
        }
    }

    public function test_planner_authorizes_intelligence_before_observation_retrieval(): void
    {
        $source = file_get_contents(base_path('app/ReadModels/Progression/Http/Controllers/ProgressionPlannerController.php'));
        self::assertIsString($source);

        $authorization = strpos($source, '->allows(');
        $rosterRetrieval = strpos($source, '$rosterEntries->forPlayer(');
        $observationRetrieval = strpos($source, '$observations->forRosterEntry(');

        self::assertIsInt($authorization);
        self::assertIsInt($rosterRetrieval);
        self::assertIsInt($observationRetrieval);
        self::assertLessThan($rosterRetrieval, $authorization, 'Alliance Intelligence authorization must occur before roster retrieval.');
        self::assertLessThan($observationRetrieval, $authorization, 'Alliance Intelligence authorization must occur before observation retrieval.');
    }

    public function test_frontend_contains_no_factual_cost_table_or_calculation_formula(): void
    {
        $source = file_get_contents(base_path('resources/js/pages/Kingdom/Progression/Planner.vue'));
        self::assertIsString($source);

        foreach ([
            'charmGuides',
            'charmDesigns',
            'gilded_threads',
            'artisans_vision',
            'upgradeSteps',
            'charmLevels',
        ] as $factualTableToken) {
            self::assertStringNotContainsString(
                $factualTableToken,
                $source,
                'Planner Vue must render typed calculator results instead of embedding factual cost data: '.$factualTableToken,
            );
        }

        self::assertStringContainsString("planner.calculation.resources", $source);
        self::assertStringContainsString("planner.calculator.status === 'calculator_ready'", $source);
    }

    /** @return array<string,string> */
    private function phpSources(string $directory): array
    {
        $sources = [];
        if (! is_dir($directory)) {
            return $sources;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            $sources[$file->getPathname()] = $contents;
        }

        ksort($sources);

        return $sources;
    }
}
