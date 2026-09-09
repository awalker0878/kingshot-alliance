<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Support\RepositoryPath;

final class ProgressionDatasetAbsenceBoundaryV3Test extends TestCase
{
    public function test_planner_handles_only_typed_release_absence_before_loading_alliance_observations(): void
    {
        $controller = file_get_contents(RepositoryPath::fromRoot('app/ReadModels/Progression/Http/Controllers/ProgressionPlannerController.php'));
        $planner = file_get_contents(RepositoryPath::fromRoot('resources/js/pages/Kingdom/Progression/Planner.vue'));

        self::assertIsString($controller);
        self::assertIsString($planner);

        self::assertStringContainsString('catch (NoProgressionDatasetPublished)', $controller);
        self::assertStringNotContainsString('catch (RuntimeException)', $controller);
        self::assertStringContainsString("'dataset' => null", $controller);
        self::assertStringContainsString('data-testid="planner-no-dataset"', $planner);

        $absenceCatch = strpos($controller, 'catch (NoProgressionDatasetPublished)');
        $scopeLookup = strpos($controller, '$scope = $allianceScopes->findForPlayer');
        self::assertIsInt($absenceCatch);
        self::assertIsInt($scopeLookup);
        self::assertLessThan($scopeLookup, $absenceCatch);
    }
}
