<?php

declare(strict_types=1);

namespace Tests\Architecture\Concerns;

trait RepositoryDocumentationLinkAssertions
{
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

                if (str_contains($target, '<') || str_contains($target, '>') || str_contains($target, '*')) {
                    continue;
                }

                if ($this->isDeferredP10LegacyCodeContractLink($path, $target)) {
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

    public function test_documentation_contains_no_legacy_markdown_filenames(): void
    {
        $legacyNames = [];

        foreach ($this->documentationFiles() as $path) {
            $basename = basename($path);
            if ($basename === 'README.md') {
                continue;
            }

            $stem = substr($basename, 0, -3);
            $legacyNames[] = strtoupper(str_replace('-', '_', $stem)).'.md';
        }

        $legacyNames = array_values(array_unique($legacyNames));
        sort($legacyNames);

        $legacyReferences = [];
        $files = [
            $this->root().'/README.md',
            $this->root().'/CONTRIBUTING.md',
            ...$this->documentationFiles(),
        ];

        foreach ($files as $path) {
            $contents = file_get_contents($path);
            self::assertIsString($contents);

            foreach ($legacyNames as $target) {
                if (str_contains($contents, $target)) {
                    $legacyReferences[] = sprintf('%s -> %s', $this->relativePath($path), $target);
                }
            }
        }

        sort($legacyReferences);
        self::assertSame([], $legacyReferences, "Legacy Markdown filename references:\n".implode("\n", $legacyReferences));
    }

    private function isDeferredP10LegacyCodeContractLink(string $sourcePath, string $target): bool
    {
        $relativeSource = $this->relativePath($sourcePath);

        return preg_match('#^docs/domains/[^/]+/README\.md$#', $relativeSource) === 1
            && preg_match('#^\.\./\.\./\.\./app/Domain/[^/]+/README\.md$#', $target) === 1;
    }
}
