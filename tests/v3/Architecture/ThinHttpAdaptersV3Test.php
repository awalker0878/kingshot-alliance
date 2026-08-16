<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ThinHttpAdaptersV3Test extends TestCase
{
    #[Test]
    public function http_adapters_and_routes_do_not_own_business_persistence_or_locks(): void
    {
        $repository = dirname(__DIR__, 3);
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($repository.'/app'),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            if (str_contains($path, '/Http/')) {
                $files[] = $file->getPathname();
            }
        }

        foreach (glob($repository.'/routes/*.php') ?: [] as $routeFile) {
            $files[] = $routeFile;
        }

        $patterns = [
            'DB::transaction' => '/\bDB::transaction\s*\(/',
            'lockForUpdate' => '/->lockForUpdate\s*\(/',
            'sharedLock' => '/->sharedLock\s*\(/',
            'model save' => '/->save\s*\(/',
            'model delete' => '/->delete\s*\(/',
            'direct create' => '/::(?:query\(\)->)?create\s*\(/',
            'direct update' => '/->update\s*\(/',
            'direct insert' => '/->insert\s*\(/',
            'direct upsert' => '/->upsert\s*\(/',
            'forceFill mutation' => '/->forceFill\s*\(/',
        ];

        $violations = [];

        foreach (array_values(array_unique($files)) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);

            foreach ($patterns as $label => $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = str_replace($repository.'/', '', $file).' ['.$label.']';
                }
            }
        }

        self::assertSame(
            [],
            $violations,
            "HTTP adapters must validate/dispatch/render only. Move these writes/locks into owner Actions:\n".implode("\n", $violations),
        );
    }
}
