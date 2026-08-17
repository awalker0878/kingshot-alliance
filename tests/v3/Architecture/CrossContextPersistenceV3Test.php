<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CrossContextPersistenceV3Test extends TestCase
{
    #[Test]
    public function business_contexts_do_not_import_foreign_context_models(): void
    {
        $repository = dirname(__DIR__, 3);
        $contextsRoot = $repository.'/app/Contexts';
        $violations = [];

        foreach (glob($contextsRoot.'/*', GLOB_ONLYDIR) ?: [] as $contextPath) {
            $context = basename($contextPath);
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($contextPath));

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                self::assertIsString($contents);

                if (preg_match_all(
                    '/^use App\\\\Contexts\\\\([A-Za-z0-9_]+)\\\\[^;]*\\\\Models\\\\[^;]+;/m',
                    $contents,
                    $matches,
                    PREG_SET_ORDER,
                ) === false) {
                    self::fail('Unable to inspect '.$file->getPathname());
                }

                foreach ($matches as $match) {
                    $foreignContext = $match[1];
                    if ($foreignContext === $context) {
                        continue;
                    }

                    $violations[] = sprintf(
                        '%s -> %s model import [%s]',
                        str_replace($repository.'/', '', $file->getPathname()),
                        $foreignContext,
                        trim($match[0]),
                    );
                }
            }
        }

        sort($violations);

        self::assertSame(
            [],
            $violations,
            "Contexts must collaborate through scalar IDs and owner contracts, not foreign Models:\n".implode("\n", $violations),
        );
    }

    #[Test]
    public function context_models_do_not_declare_eloquent_relations_to_foreign_contexts(): void
    {
        $repository = dirname(__DIR__, 3);
        $contextsRoot = $repository.'/app/Contexts';
        $violations = [];

        foreach (glob($contextsRoot.'/*', GLOB_ONLYDIR) ?: [] as $contextPath) {
            $context = basename($contextPath);
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($contextPath));

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                if (! str_contains($path, '/Models/')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                self::assertIsString($contents);

                if (preg_match_all(
                    '/^use App\\\\Contexts\\\\([A-Za-z0-9_]+)\\\\[^;]*\\\\Models\\\\([^;]+);/m',
                    $contents,
                    $matches,
                    PREG_SET_ORDER,
                ) === false) {
                    self::fail('Unable to inspect '.$file->getPathname());
                }

                foreach ($matches as $match) {
                    if ($match[1] === $context) {
                        continue;
                    }

                    $model = trim($match[2]);
                    if (preg_match('/(?:belongsTo|hasOne|hasMany|belongsToMany|morphTo|morphOne|morphMany)\\s*\\(\\s*'.preg_quote($model, '/').'::class/', $contents) === 1) {
                        $violations[] = sprintf(
                            '%s -> %s\\%s Eloquent relationship',
                            str_replace($repository.'/', '', $file->getPathname()),
                            $match[1],
                            $model,
                        );
                    }
                }
            }
        }

        sort($violations);

        self::assertSame(
            [],
            $violations,
            "Context Models must not navigate into foreign aggregates:\n".implode("\n", $violations),
        );
    }
}
