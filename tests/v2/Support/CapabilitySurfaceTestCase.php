<?php

declare(strict_types=1);

namespace Tests\v2\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Tests\v2\TestCase;

abstract class CapabilitySurfaceTestCase extends TestCase
{
    protected const CAPABILITY = '';

    /** @var list<string> */
    protected const SOURCES = [];

    protected const DOCUMENTATION = '';

    public function test_documented_capability_and_source_surface_exist(): void
    {
        self::assertNotSame('', static::CAPABILITY);
        self::assertNotSame([], static::SOURCES);
        self::assertNotSame('', static::DOCUMENTATION);

        $documentation = base_path(static::DOCUMENTATION);
        self::assertFileExists($documentation, static::CAPABILITY.' documentation is missing.');
        self::assertStringContainsString('Status: Current', (string) file_get_contents($documentation));

        foreach (static::SOURCES as $source) {
            self::assertDirectoryExists(base_path($source), static::CAPABILITY.' source is missing: '.$source);
        }

        self::assertNotSame([], $this->phpFiles(), static::CAPABILITY.' has no PHP implementation surface.');
    }

    public function test_every_php_symbol_in_the_capability_autoloads(): void
    {
        foreach ($this->phpFiles() as $file) {
            $symbol = $this->symbolFor($file);
            $loaded = class_exists($symbol)
                || interface_exists($symbol)
                || trait_exists($symbol)
                || enum_exists($symbol);

            self::assertTrue($loaded, static::CAPABILITY.' symbol does not autoload: '.$symbol);
        }
    }

    public function test_capability_models_map_to_the_fresh_schema(): void
    {
        $files = $this->phpFiles();
        self::assertNotSame([], $files, static::CAPABILITY.' has no implementation surface to inspect for models.');

        foreach ($files as $file) {
            if (! str_contains(str_replace('\\', '/', $file), '/Models/')) {
                continue;
            }

            $symbol = $this->symbolFor($file);
            if (! class_exists($symbol) || ! is_subclass_of($symbol, Model::class)) {
                continue;
            }

            $reflection = new ReflectionClass($symbol);
            if (! $reflection->isInstantiable()) {
                continue;
            }

            /** @var Model $model */
            $model = $reflection->newInstance();
            self::assertTrue(
                Schema::hasTable($model->getTable()),
                static::CAPABILITY.' model has no fresh-schema table: '.$symbol.' -> '.$model->getTable(),
            );
        }
    }

    public function test_actions_services_queries_and_http_classes_expose_public_contracts(): void
    {
        $files = $this->phpFiles();
        self::assertNotSame([], $files, static::CAPABILITY.' has no implementation surface to inspect for public contracts.');

        foreach ($files as $file) {
            $normalized = str_replace('\\', '/', $file);
            if (! preg_match('#/(Actions|Services|Queries|Http)/#', $normalized)) {
                continue;
            }

            $symbol = $this->symbolFor($file);
            if (! class_exists($symbol)) {
                continue;
            }

            $reflection = new ReflectionClass($symbol);
            if (! $reflection->isInstantiable()) {
                continue;
            }

            $public = array_filter(
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
                static fn ($method): bool => $method->getDeclaringClass()->getName() === $symbol
                    && ($method->getName() === '__invoke' || ! str_starts_with($method->getName(), '__')),
            );

            self::assertNotSame([], array_values($public), static::CAPABILITY.' public surface is empty: '.$symbol);
        }
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $files = [];

        foreach (static::SOURCES as $source) {
            $root = base_path($source);
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            /** @var SplFileInfo $entry */
            foreach ($iterator as $entry) {
                if (! $entry->isFile() || $entry->getExtension() !== 'php') {
                    continue;
                }
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    private function symbolFor(string $file): string
    {
        $app = str_replace('\\', '/', base_path('app')).'/';
        $normalized = str_replace('\\', '/', $file);
        self::assertStringStartsWith($app, $normalized);
        $relative = substr($normalized, strlen($app), -4);

        return 'App\\'.str_replace('/', '\\', $relative);
    }
}
