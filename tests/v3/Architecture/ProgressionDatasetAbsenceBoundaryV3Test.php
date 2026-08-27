<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use Tests\v3\TestCase;

final class ProgressionDatasetAbsenceBoundaryV3Test extends TestCase
{
    public function test_no_dataset_state_is_reserved_for_true_release_absence(): void
    {
        $query = file_get_contents(base_path('app/Contexts/GameWorld/Progression/Queries/ProgressionDatasetQuery.php'));
        $controller = file_get_contents(base_path('app/ReadModels/Progression/Http/Controllers/ProgressionPlannerController.php'));
        $planner = file_get_contents(base_path('resources/js/pages/Kingdom/Progression/Planner.vue'));

        self::assertIsString($query);
        self::assertIsString($controller);
        self::assertIsString($planner);

        self::assertStringContainsString('NoProgressionDatasetPublished', $query);
        self::assertStringContainsString("throw new NoProgressionDatasetPublished('No factual progression dataset is published.');", $query);
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

    public function test_published_dataset_integrity_failures_are_not_collapsed_into_absence(): void
    {
        $query = file_get_contents(base_path('app/Contexts/GameWorld/Progression/Queries/ProgressionDatasetQuery.php'));
        self::assertIsString($query);

        self::assertStringContainsString('Factual progression dataset does not satisfy a supported schema.', $query);
        self::assertStringContainsString('Progression schema v2 release omitted required file:', $query);
        self::assertStringContainsString('Factual progression dataset contains invalid JSON:', $query);
    }
}
