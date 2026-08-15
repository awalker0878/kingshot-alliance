<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ArchitectureV2DependencyTest extends TestCase
{
    /** @var list<string> */
    private const CONTEXTS = [
        'Accounts',
        'GameWorld',
        'Alliance',
        'Operations',
        'Intelligence',
        'Communications',
        'Platform',
    ];

    /** @var list<string> */
    private const V1_DOMAIN_ROOTS = [
        'Alliances',
        'Audit',
        'Authorization',
        'Content',
        'Contributions',
        'Events',
        'Identity',
        'Integrations',
        'KingPerks',
        'Kingdoms',
        'Memberships',
        'Notifications',
        'Platform',
        'Rallies',
        'Recruitment',
    ];

    public function test_v2_application_roots_exist(): void
    {
        foreach (self::CONTEXTS as $context) {
            self::assertDirectoryExists($this->root().'/app/Contexts/'.$context);
        }

        self::assertDirectoryExists($this->root().'/app/Shared');
        self::assertDirectoryExists($this->root().'/app/Workflows');
        self::assertDirectoryExists($this->root().'/app/ReadModels');
    }

    public function test_no_new_v1_domain_roots_may_be_added_during_the_rewrite(): void
    {
        $domainRoot = $this->root().'/app/Domain';

        if (!is_dir($domainRoot)) {
            self::assertTrue(true);

            return;
        }

        $actual = [];

        foreach (new \DirectoryIterator($domainRoot) as $entry) {
            if ($entry->isDir() && !$entry->isDot()) {
                $actual[] = $entry->getFilename();
            }
        }

        foreach ($actual as $domain) {
            self::assertContains(
                $domain,
                self::V1_DOMAIN_ROOTS,
                $domain.' is a new V1 domain root. New behavior belongs under app/Contexts.',
            );
        }
    }

    public function test_v2_php_files_use_their_v2_namespace_root(): void
    {
        $roots = [
            $this->root().'/app/Contexts' => 'App\\Contexts',
            $this->root().'/app/Shared' => 'App\\Shared',
            $this->root().'/app/Workflows' => 'App\\Workflows',
            $this->root().'/app/ReadModels' => 'App\\ReadModels',
        ];

        foreach ($roots as $directory => $namespaceRoot) {
            foreach ($this->phpFiles($directory) as $file) {
                self::assertStringContainsString(
                    'namespace '.$namespaceRoot,
                    $this->source($file),
                    $file.' must use its Architecture V2 namespace root.',
                );
            }
        }
    }

    public function test_v2_code_never_imports_the_v1_domain_tree(): void
    {
        foreach ($this->v2PhpFiles() as $file) {
            $source = $this->source($file);

            self::assertStringNotContainsString(
                'App\\Domain\\',
                $source,
                $file.' must be rewritten to a V2 contract instead of depending on App\\Domain.',
            );
        }
    }

    public function test_shared_does_not_depend_on_business_contexts_or_orchestration(): void
    {
        $this->assertFilesDoNotImport(
            $this->phpFiles($this->root().'/app/Shared'),
            [
                'App\\Contexts\\',
                'App\\Workflows\\',
                'App\\ReadModels\\',
                'App\\Domain\\',
            ],
        );
    }

    public function test_accounts_is_foundational(): void
    {
        $this->assertFilesDoNotImport(
            $this->phpFiles($this->root().'/app/Contexts/Accounts'),
            [
                'App\\Contexts\\GameWorld\\',
                'App\\Contexts\\Alliance\\',
                'App\\Contexts\\Operations\\',
                'App\\Contexts\\Intelligence\\',
                'App\\Contexts\\Communications\\',
                'App\\Contexts\\Platform\\',
            ],
        );
    }

    public function test_game_world_never_depends_on_higher_level_feature_contexts(): void
    {
        $this->assertFilesDoNotImport(
            $this->phpFiles($this->root().'/app/Contexts/GameWorld'),
            [
                'App\\Contexts\\Alliance\\',
                'App\\Contexts\\Operations\\',
                'App\\Contexts\\Intelligence\\',
                'App\\Contexts\\Communications\\',
                'App\\Contexts\\Platform\\',
            ],
        );
    }

    public function test_alliance_does_not_depend_on_downstream_operational_or_intelligence_contexts(): void
    {
        $this->assertFilesDoNotImport(
            $this->phpFiles($this->root().'/app/Contexts/Alliance'),
            [
                'App\\Contexts\\Operations\\',
                'App\\Contexts\\Intelligence\\',
                'App\\Contexts\\Communications\\',
                'App\\Contexts\\Platform\\',
            ],
        );
    }

    public function test_operations_does_not_depend_on_downstream_intelligence_or_delivery_contexts(): void
    {
        $this->assertFilesDoNotImport(
            $this->phpFiles($this->root().'/app/Contexts/Operations'),
            [
                'App\\Contexts\\Intelligence\\',
                'App\\Contexts\\Communications\\',
                'App\\Contexts\\Platform\\',
            ],
        );
    }

    public function test_intelligence_does_not_depend_on_delivery_or_platform_contexts(): void
    {
        $this->assertFilesDoNotImport(
            $this->phpFiles($this->root().'/app/Contexts/Intelligence'),
            [
                'App\\Contexts\\Communications\\',
                'App\\Contexts\\Platform\\',
            ],
        );
    }

    public function test_communications_does_not_depend_on_platform_business_state(): void
    {
        $this->assertFilesDoNotImport(
            $this->phpFiles($this->root().'/app/Contexts/Communications'),
            ['App\\Contexts\\Platform\\'],
        );
    }

    public function test_workflows_and_read_models_are_v2_only(): void
    {
        foreach (['Workflows', 'ReadModels'] as $root) {
            $this->assertFilesDoNotImport(
                $this->phpFiles($this->root().'/app/'.$root),
                ['App\\Domain\\'],
            );
        }
    }

    public function test_v2_contains_no_compatibility_namespaces_or_class_aliases(): void
    {
        foreach ($this->v2PhpFiles() as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($this->root()) + 1));
            $source = $this->source($file);

            self::assertDoesNotMatchRegularExpression(
                '#/(Legacy|Compatibility|Compat)(/|$)#',
                '/'.$relative,
                $relative.' introduces a compatibility namespace/path.',
            );
            self::assertStringNotContainsString(
                'class_alias(',
                $source,
                $relative.' must not introduce namespace compatibility aliases.',
            );
        }
    }

    /**
     * @param list<string> $files
     * @param list<string> $forbiddenImports
     */
    private function assertFilesDoNotImport(array $files, array $forbiddenImports): void
    {
        foreach ($files as $file) {
            $source = $this->source($file);

            foreach ($forbiddenImports as $forbiddenImport) {
                self::assertStringNotContainsString(
                    $forbiddenImport,
                    $source,
                    $file.' violates the Architecture V2 dependency direction with '.$forbiddenImport,
                );
            }
        }
    }

    /** @return list<string> */
    private function v2PhpFiles(): array
    {
        $files = [];

        foreach (['Contexts', 'Shared', 'Workflows', 'ReadModels'] as $root) {
            array_push($files, ...$this->phpFiles($this->root().'/app/'.$root));
        }

        sort($files);

        return $files;
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function source(string $file): string
    {
        $source = file_get_contents($file);
        self::assertIsString($source);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
