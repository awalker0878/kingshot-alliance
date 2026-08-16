<?php

declare(strict_types=1);

namespace Tests\Architecture\V2;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\V2\ArchitectureCatalogue;
use Tests\Support\V2\RepositoryInspector;

final class ApplicationShapeV2Test extends TestCase
{
    public function test_only_final_application_roots_are_present(): void
    {
        $root = RepositoryInspector::root();

        foreach (array_keys(ArchitectureCatalogue::contextCapabilities()) as $context) {
            self::assertDirectoryExists($root.'/app/Contexts/'.$context);
        }

        self::assertDirectoryExists($root.'/app/Shared');
        self::assertDirectoryExists($root.'/app/Workflows');
        self::assertDirectoryExists($root.'/app/ReadModels');
        self::assertDirectoryDoesNotExist($root.'/app/Domain');
    }

    public function test_context_catalogue_matches_the_live_top_level_capability_shape(): void
    {
        foreach (ArchitectureCatalogue::contextCapabilities() as $context => $capabilities) {
            $expected = ArchitectureCatalogue::structuralChildren()[$context] ?? [];
            foreach ($capabilities as $capability) {
                if ($capability !== 'Core') {
                    $expected[] = $capability;
                }
            }
            sort($expected);

            self::assertSame(
                $expected,
                RepositoryInspector::childDirectories('app/Contexts/'.$context),
                $context.' contains an uncatalogued capability or structural directory. Update the V2 catalogue and its tests deliberately.',
            );
        }
    }

    public function test_workflow_catalogue_matches_live_workflows(): void
    {
        $expected = ArchitectureCatalogue::workflows();
        sort($expected);

        self::assertSame($expected, RepositoryInspector::childDirectories('app/Workflows'));
    }

    public function test_read_model_catalogue_matches_live_read_models(): void
    {
        $expected = ArchitectureCatalogue::readModels();
        sort($expected);

        self::assertSame($expected, RepositoryInspector::childDirectories('app/ReadModels'));
    }

    public function test_shared_catalogue_matches_live_shared_capabilities(): void
    {
        $expected = ArchitectureCatalogue::sharedCapabilities();
        sort($expected);

        self::assertSame($expected, RepositoryInspector::childDirectories('app/Shared'));
    }

    public function test_rewritten_tests_are_visibly_marked_as_v2_in_path_namespace_and_class_name(): void
    {
        foreach (['tests/Architecture/V2', 'tests/Feature'] as $directory) {
            foreach (RepositoryInspector::phpFiles($directory) as $file) {
                $relative = str_replace('\\', '/', RepositoryInspector::relative($file));

                if (! str_contains('/'.$relative, '/V2/')) {
                    continue;
                }

                self::assertStringEndsWith('V2Test.php', basename($relative), $relative.' must end in V2Test.php.');
                self::assertStringContainsString('\\V2;', RepositoryInspector::source($file), $relative.' must live in a V2 namespace.');
                self::assertMatchesRegularExpression('/final class [A-Za-z0-9_]+V2Test extends TestCase/', RepositoryInspector::source($file), $relative.' must use a V2Test class name.');
            }
        }
    }

    #[DataProvider('namespaceRoots')]
    public function test_php_files_follow_their_final_namespace_root(string $directory, string $namespace): void
    {
        foreach (RepositoryInspector::phpFiles($directory) as $file) {
            self::assertStringContainsString(
                'namespace '.$namespace,
                RepositoryInspector::source($file),
                RepositoryInspector::relative($file).' is outside its final namespace.',
            );
        }
    }

    /** @return array<string, array{string, string}> */
    public static function namespaceRoots(): array
    {
        return [
            'contexts' => ['app/Contexts', 'App\\Contexts'],
            'shared' => ['app/Shared', 'App\\Shared'],
            'workflows' => ['app/Workflows', 'App\\Workflows'],
            'read-models' => ['app/ReadModels', 'App\\ReadModels'],
        ];
    }
}
