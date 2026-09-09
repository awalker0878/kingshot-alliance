<?php

declare(strict_types=1);

namespace Tests\v3;

use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Throwable;

abstract class TestCase extends LaravelTestCase
{
    /** @var array{process:string|false,env:mixed,server:mixed}|null */
    private ?array $originalCachePrefix = null;

    protected function setUp(): void
    {
        $this->originalCachePrefix = [
            'process' => getenv('CACHE_PREFIX'),
            'env' => $_ENV['CACHE_PREFIX'] ?? null,
            'server' => $_SERVER['CACHE_PREFIX'] ?? null,
        ];
        // Providers resolve the limiter during boot. Set the namespace before
        // creating the application, and keep it for this test's app reboots.
        $prefix = 'kingshot-test-'.bin2hex(random_bytes(16)).'-';
        putenv('CACHE_PREFIX='.$prefix);
        $_ENV['CACHE_PREFIX'] = $_SERVER['CACHE_PREFIX'] = $prefix;

        try {
            parent::setUp();

            $this->withoutVite();
        } catch (Throwable $exception) {
            $this->restoreCacheEnvironment();
            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->restoreCacheEnvironment();
        }
    }

    private function restoreCacheEnvironment(): void
    {
        if ($this->originalCachePrefix === null) {
            return;
        }

        $previous = $this->originalCachePrefix;
        putenv($previous['process'] === false ? 'CACHE_PREFIX' : 'CACHE_PREFIX='.$previous['process']);
        if ($previous['env'] === null) {
            unset($_ENV['CACHE_PREFIX']);
        } else {
            $_ENV['CACHE_PREFIX'] = $previous['env'];
        }
        if ($previous['server'] === null) {
            unset($_SERVER['CACHE_PREFIX']);
        } else {
            $_SERVER['CACHE_PREFIX'] = $previous['server'];
        }

        $this->originalCachePrefix = null;
    }
}
