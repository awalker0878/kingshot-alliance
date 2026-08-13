<?php

declare(strict_types=1);

namespace Tests\Architecture\Concerns;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

trait RepositoryStructureSupport
{
    /** @return array<string, list<string>> */
    private function requiredDcpP2FocusedSecurityReviews(): array
    {
        return [
            'alliances' => ['tenant-context-security-review.md'],
            'content' => ['media-security-review.md'],
            'identity' => ['mfa-and-recovery-security-review.md'],
            'integrations' => ['api-security-review.md', 'webhooks-security-review.md'],
            'memberships' => ['invitations-security-review.md'],
            'platform' => ['lifecycle-and-retention-security-review.md', 'transactional-outbox-security-review.md'],
            'recruitment' => ['application-intake-security-review.md'],
        ];
    }

    /** @param list<string> $headings */
    private function assertHeadingsAppearInOrder(string $contents, array $headings, string $path): void
    {
        $lastPosition = null;

        foreach ($headings as $heading) {
            $position = strpos($contents, $heading);

            self::assertNotFalse(
                $position,
                sprintf('Missing required heading "%s" in %s', $heading, $this->relativePath($path)),
            );

            if ($lastPosition !== null) {
                self::assertGreaterThan(
                    $lastPosition,
                    $position,
                    sprintf('Required headings are out of order in %s', $this->relativePath($path)),
                );
            }

            $lastPosition = $position;
        }
    }

    /** @return list<string> */
    private function documentationFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root().'/docs', FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (($file instanceof SplFileInfo) === false || $file->isFile() === false || $file->getExtension() !== 'md') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /** @return list<string> */
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

    private function kebabCase(string $value): string
    {
        $kebab = preg_replace('/(?<!^)[A-Z]/', '-$0', $value);

        self::assertIsString($kebab);

        return strtolower($kebab);
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace($this->root(), '', $path), '/');
    }

    private function root(): string
    {
        return dirname(__DIR__, 3);
    }
}
