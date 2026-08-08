<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class RepositoryStructureTest extends TestCase
{
    public function test_documentation_uses_only_the_implementation_plan_groups(): void
    {
        self::assertSame(
            ['adr', 'domains', 'operations', 'product', 'security'],
            $this->directories($this->root().'/docs'),
        );
    }

    public function test_documentation_groups_have_navigation_indexes(): void
    {
        foreach ([
            'docs/README.md',
            'docs/adr/README.md',
            'docs/domains/README.md',
            'docs/operations/README.md',
            'docs/product/README.md',
            'docs/security/README.md',
        ] as $path) {
            self::assertFileExists($this->root().'/'.$path, sprintf('Missing documentation index: %s', $path));
        }
    }

    public function test_test_suite_uses_only_the_implementation_plan_groups(): void
    {
        self::assertSame(
            ['Architecture', 'Feature', 'Integration', 'Performance', 'TenantIsolation', 'Unit'],
            $this->directories($this->root().'/tests'),
        );
    }

    /**
     * @return list<string>
     */
    private function directories(string $path): array
    {
        $entries = scandir($path);

        self::assertIsArray($entries);

        $directories = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '.'
                && $entry !== '..'
                && is_dir($path.'/'.$entry),
        ));

        sort($directories);

        return $directories;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
