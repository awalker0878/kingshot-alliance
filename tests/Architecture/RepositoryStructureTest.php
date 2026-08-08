<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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

    public function test_documentation_filenames_are_predictable(): void
    {
        $invalid = [];

        foreach ($this->documentationFiles() as $path) {
            $basename = basename($path);

            if ($basename === 'README.md') {
                continue;
            }

            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*\.md$/', $basename) !== 1) {
                $invalid[] = $this->relativePath($path);
            }
        }

        self::assertSame([], $invalid, 'Documentation filenames must use lowercase kebab-case; README.md is reserved for indexes.');
    }

    public function test_local_markdown_document_links_resolve(): void
    {
        $broken = [];
        $files = [
            $this->root().'/README.md',
            $this->root().'/CONTRIBUTING.md',
            ...$this->documentationFiles(),
        ];

        foreach ($files as $path) {
            $contents = file_get_contents($path);
            self::assertIsString($contents);

            preg_match_all('/\]\((?!https?:\/\/|mailto:)([^)#\s]+\.md)(?:#[^)]+)?\)/', $contents, $matches);

            foreach ($matches[1] ?? [] as $target) {
                if (! is_string($target)) {
                    continue;
                }

                $resolved = dirname($path).'/'.rawurldecode($target);

                if (! is_file($resolved)) {
                    $broken[] = sprintf('%s -> %s', $this->relativePath($path), $target);
                }
            }
        }

        sort($broken);

        self::assertSame([], $broken, "Broken local Markdown links:\n".implode("\n", $broken));
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
    private function documentationFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root().'/docs', FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
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

    private function relativePath(string $path): string
    {
        return ltrim(str_replace($this->root(), '', $path), '/');
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
