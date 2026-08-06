<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->validateRuntimeConfiguration();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for(
            'api',
            static fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip())
        );
    }

    private function validateRuntimeConfiguration(): void
    {
        if (! $this->app->environment(['staging', 'production'])) {
            return;
        }

        $required = config('operations.required_configuration', []);

        if (! is_array($required)) {
            throw new RuntimeException('The required runtime configuration list is invalid.');
        }

        $missing = collect($required)
            ->filter(static fn (mixed $key): bool => is_string($key) && blank(config($key)))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException(
                'Missing required runtime configuration: '.$missing->implode(', ')
            );
        }
    }
}
