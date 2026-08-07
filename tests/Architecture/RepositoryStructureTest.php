<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class RepositoryStructureTest extends TestCase
{
    public function test_documentation_uses_the_implementation_plan_groups(): void
    {
        foreach (['adr', 'domains', 'operations', 'product', 'security'] as $directory) {
            self::assertDirectoryExists($this->root().'/docs/'.$directory);
        }

        self::assertDirectoryDoesNotExist($this->root().'/docs/architecture');
        self::assertDirectoryDoesNotExist($this->root().'/docs/runbooks');
    }

    public function test_test_suite_uses_the_implementation_plan_groups(): void
    {
        foreach (['Architecture', 'Feature', 'Integration', 'Performance', 'TenantIsolation', 'Unit'] as $directory) {
            self::assertDirectoryExists($this->root().'/tests/'.$directory);
        }
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
