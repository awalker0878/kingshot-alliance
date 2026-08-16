<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class TestingEvidenceDocumentationTest extends TestCase
{
    public function test_codebase_testing_documentation_maps_current_v2_evidence(): void
    {
        $testing = $this->read('docs/codebase/testing.md');

        foreach ([
            'no runtime `App\\Domain\\*` imports',
            'app/Contexts',
            'app/Workflows',
            'app/ReadModels',
            'app/Shared/Infrastructure',
            'Player persistence ownership',
            'PostgreSQL migrations',
            'Pint',
            'Larastan',
        ] as $evidence) {
            self::assertStringContainsString($evidence, $testing, $evidence);
        }
    }

    public function test_phpunit_suite_directories_remain_real_code_not_historical_documentation(): void
    {
        foreach (['Architecture', 'Feature', 'Integration', 'Performance', 'TenantIsolation', 'Unit'] as $suite) {
            self::assertDirectoryExists($this->root().'/tests/'.$suite);
        }

        self::assertDirectoryDoesNotExist($this->root().'/docs/domains');
    }

    public function test_definition_of_done_requires_current_behavioral_and_operational_evidence(): void
    {
        $dod = $this->read('docs/governance/definition-of-done.md');

        foreach (['authorization', 'concurrency', 'idempotency', 'static analysis', 'production frontend build', 'Architecture V2'] as $evidence) {
            self::assertStringContainsString($evidence, $dod);
        }
    }

    public function test_historical_phase_run_ids_are_not_required_as_living_documentation(): void
    {
        $standard = strtolower($this->read('docs/governance/documentation-standard.md'));

        self::assertStringContainsString('git history is the archive', $standard);
        self::assertStringContainsString('superseded phase', $standard);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root().'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
