<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class RepositoryStructureTest extends TestCase
{
    public function test_v2_application_roots_are_explicit_and_v1_domain_root_is_absent(): void
    {
        foreach (['Contexts', 'Workflows', 'ReadModels', 'Shared'] as $root) {
            self::assertDirectoryExists($this->root().'/app/'.$root);
        }

        self::assertDirectoryDoesNotExist($this->root().'/app/Domain');
    }

    public function test_business_context_roots_match_the_v2_context_inventory(): void
    {
        self::assertSame([
            'Accounts',
            'Alliance',
            'Communications',
            'GameWorld',
            'Intelligence',
            'Operations',
            'Platform',
        ], $this->directories($this->root().'/app/Contexts'));
    }

    public function test_documentation_root_matches_reader_intent_structure(): void
    {
        self::assertSame([
            'architecture',
            'codebase',
            'governance',
            'operations',
            'product',
            'reference',
        ], $this->directories($this->root().'/docs'));
    }

    public function test_repository_navigation_points_to_current_documentation(): void
    {
        $readme = $this->read('README.md');
        $contributing = $this->read('CONTRIBUTING.md');

        foreach (['docs/architecture/README.md', 'docs/codebase/README.md', 'docs/operations/README.md', 'docs/governance/README.md'] as $path) {
            self::assertStringContainsString($path, $readme, $path);
        }

        self::assertStringContainsString('docs/governance/documentation-standard.md', $contributing);
        self::assertStringNotContainsString('docs/domains/', $readme);
        self::assertStringNotContainsString('docs/product/implementation-plan.md', $contributing);
    }

    /** @return list<string> */
    private function directories(string $path): array
    {
        $entries = scandir($path);
        self::assertIsArray($entries);

        $directories = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($path.'/'.$entry),
        ));
        sort($directories);

        return $directories;
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
