<?php

declare(strict_types=1);

namespace Tests\Support\V2;

final class RepositoryInspector
{
    public static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @return list<string> */
    public static function phpFiles(string $relativeDirectory): array
    {
        $directory = self::root().'/'.trim($relativeDirectory, '/');

        if (! is_dir($directory)) {
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

    /** @return list<string> */
    public static function childDirectories(string $relativeDirectory): array
    {
        $directory = self::root().'/'.trim($relativeDirectory, '/');

        if (! is_dir($directory)) {
            return [];
        }

        $children = [];
        foreach (new \DirectoryIterator($directory) as $entry) {
            if ($entry->isDir() && ! $entry->isDot()) {
                $children[] = $entry->getFilename();
            }
        }

        sort($children);

        return $children;
    }

    public static function source(string $absoluteFile): string
    {
        $source = file_get_contents($absoluteFile);

        if ($source === false) {
            throw new \RuntimeException('Unable to read '.$absoluteFile);
        }

        return $source;
    }

    public static function absolute(string $relativePath): string
    {
        return self::root().'/'.ltrim($relativePath, '/');
    }

    public static function relative(string $absolutePath): string
    {
        return str_replace('\\', '/', substr($absolutePath, strlen(self::root()) + 1));
    }
}
