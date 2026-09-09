<?php

declare(strict_types=1);

namespace Tests\v3\Infrastructure;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\TestCase;

final class CacheNamespaceIsolationV3Test extends TestCase
{
    private static ?string $previousNamespace = null;

    /** @return iterable<string,array{string}> */
    public static function consumers(): iterable
    {
        yield 'first test consumer' => ['first'];
        yield 'second test consumer' => ['second'];
    }

    #[DataProvider('consumers')]
    public function test_independent_cases_do_not_inherit_cached_values_or_rate_limit_attempts(string $consumer): void
    {
        $namespace = (string) config('cache.prefix');
        self::assertNotSame(self::$previousNamespace, $namespace);
        self::$previousNamespace = $namespace;
        self::assertNull(Cache::get('namespace-isolation-probe'), 'Cached state leaked into '.$consumer);
        Cache::put('namespace-isolation-probe', $consumer, 60);
        RateLimiter::hit('namespace-isolation-budget', 60);
        self::assertSame(1, (int) RateLimiter::attempts('namespace-isolation-budget'));
        self::assertSame($consumer, Cache::get('namespace-isolation-probe'));
        // Leave expiring entries behind: the next case must be isolated even
        // when a prior case's persistent Redis state has not been deleted.
    }

    public function test_application_reboot_preserves_namespace_and_the_configured_cache_driver(): void
    {
        $namespace = config('cache.prefix');
        $driver = config('cache.default');
        RateLimiter::hit('same-test-reboot-budget', 60);
        $this->refreshApplication();

        self::assertSame($namespace, config('cache.prefix'));
        self::assertSame($driver, config('cache.default'));
        // CI's Redis-backed limiter must retain state across an app reboot;
        // the sequential gate's process-local array store has no such storage.
        self::assertSame($driver === 'array' ? 0 : 1, (int) RateLimiter::attempts('same-test-reboot-budget'));
    }
}
