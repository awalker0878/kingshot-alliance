<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Identity\AllianceContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Horizon\Horizon;
use Laravel\Pulse\Pulse;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AllianceContext::class);

        Horizon::auth(static fn (): bool => false);

        $this->callAfterResolving(
            Pulse::class,
            static function (Pulse $pulse): void {
                $pulse->ignoreRoutes();
            },
        );
    }

    public function boot(): void
    {
        $this->configureRateLimiting();

        $environment = $this->app->environment();
        $appScheme = strtolower((string) parse_url((string) config('app.url'), PHP_URL_SCHEME));

        if (in_array($environment, ['staging', 'production'], true) && $appScheme === 'https') {
            URL::forceScheme('https');
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for(
            'api',
            static fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip())
        );

        RateLimiter::for(
            'login',
            static fn (Request $request): Limit => Limit::perMinute(5)->by(
                Str::lower(trim((string) $request->input('email'))).'|'.(string) $request->ip(),
            ),
        );

        RateLimiter::for(
            'registration',
            static fn (Request $request): Limit => Limit::perMinute(3)->by((string) $request->ip()),
        );
    }
}
