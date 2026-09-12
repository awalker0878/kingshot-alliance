<?php

declare(strict_types=1);

namespace Tests\Support;

/** Repository source paths without constructing the application or caching file contents. */
final class RepositoryPath
{
    public static function fromRoot(string $relativePath): string
    {
        return dirname(__DIR__, 2).'/'.$relativePath;
    }
}
