<?php

declare(strict_types=1);

namespace Tests\Architecture\Concerns;

trait RepositoryDocumentationRootAssertions
{
    public function test_domain_documentation_root_contains_only_the_navigation_index(): void
    {
        $root = $this->root().'/docs/domains';
        $entries = scandir($root);
        self::assertIsArray($entries);

        $markdownFiles = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '.'
                && $entry !== '..'
                && is_file($root.'/'.$entry)
                && pathinfo($entry, PATHINFO_EXTENSION) === 'md',
        ));
        sort($markdownFiles);

        self::assertSame(['README.md'], $markdownFiles);
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

        self::assertSame([], $invalid);
    }
}
