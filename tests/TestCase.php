<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Support\Facades\ParallelTesting;
use LogicException;
use Tests\Support\MigrationReferenceData;
use Throwable;

abstract class TestCase extends LaravelTestCase
{
    /** @var array{process:string|false,env:mixed,server:mixed}|null */
    private ?array $originalCachePrefix = null;

    private bool $committedDatabaseReady = false;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $prefix = (string) $app['config']->get('cache.prefix');
        // Laravel's parallel callback restores its first process prefix after
        // boot. Reapply this test's namespace before traits or fixtures run;
        // providers already resolved their stores against it during boot.
        ParallelTesting::setUpTestCase(static function () use ($app, $prefix): void {
            $app['config']->set('cache.prefix', $prefix);
        });

        return $app;
    }

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

    /** @return array<class-string, class-string> */
    protected function setUpTraits(): array
    {
        $uses = $this->traitsUsedByTest ?? class_uses_recursive(static::class);
        if (! isset($uses[DatabaseTruncation::class])) {
            return parent::setUpTraits();
        }
        foreach ([RefreshDatabase::class, DatabaseMigrations::class, DatabaseTransactions::class] as $other) {
            if (isset($uses[$other])) {
                throw new LogicException('DatabaseTruncation must not be combined with another database reset trait.');
            }
        }
        if ($this->connectionsToTruncate() !== [null]) {
            throw new LogicException('Committed-state tests must use the worker-isolated default database.');
        }

        $connection = $this->app->make('db')->connection();
        $fresh = ! RefreshDatabaseState::$migrated || ! MigrationReferenceData::captured($connection);
        if ($fresh) {
            // A previous transactional test may own the migrated flag without
            // owning a reference snapshot. Rebuild once, never snapshot its
            // potentially mutated data. Later tests reuse this worker's schema.
            RefreshDatabaseState::$migrated = false;
        }

        try {
            $uses = parent::setUpTraits();
            if ($fresh) {
                MigrationReferenceData::captureFresh($connection);
            } else {
                MigrationReferenceData::restore($connection);
            }
            $this->committedDatabaseReady = true;
        } catch (Throwable $exception) {
            RefreshDatabaseState::$migrated = false;
            throw $exception;
        }

        return $uses;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->app !== null && $this->committedDatabaseReady) {
                $this->resetCommittedDatabase();
            }
        } finally {
            $this->committedDatabaseReady = false;
            try {
                // Framework callbacks and mock cleanup must run even if the
                // database cleanup above fails. Never leave a dirty migrated
                // flag available to the next test in this worker.
                parent::tearDown();
            } finally {
                $this->restoreCacheEnvironment();
            }
        }
    }

    /** Clean committed fixtures and restore migration data before a trait handoff. */
    protected function resetCommittedDatabase(): void
    {
        try {
            $this->truncateTablesForAllConnections();
            MigrationReferenceData::restore($this->app->make('db')->connection());
        } catch (Throwable $exception) {
            RefreshDatabaseState::$migrated = false;
            throw $exception;
        }
    }

    /** @return list<string|null> Overridden by the framework truncation trait. */
    protected function connectionsToTruncate(): array
    {
        return [null];
    }

    /**
     * DatabaseTruncation declares the real implementation in subclasses using
     * that trait. Keeping the inherited hook as a no-op lets the shared base
     * invoke it without changing non-truncation tests.
     */
    protected function truncateTablesForAllConnections(): void
    {
        // Overridden by Illuminate\Foundation\Testing\DatabaseTruncation.
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
