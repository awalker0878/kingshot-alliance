<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Providers;

use App\Shared\Infrastructure\Uploads\Services\BasicUploadScanner;
use App\Shared\Infrastructure\Uploads\Services\UploadScanner;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Pulse;

final class InfrastructureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UploadScanner::class, BasicUploadScanner::class);
        $this->callAfterResolving(Pulse::class, static function (Pulse $pulse): void {
            $pulse->ignoreRoutes();
        });
    }

    public function boot(): void
    {
        $trustedProxies = trim((string) config('operations.trusted_proxies', ''));
        if ($trustedProxies !== '') {
            TrustProxies::at($trustedProxies);
            TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        }

        $environment = $this->app->environment();
        $appScheme = strtolower((string) parse_url((string) config('app.url'), PHP_URL_SCHEME));
        if (in_array($environment, ['staging', 'production'], true) && $appScheme === 'https') {
            URL::forceScheme('https');
        }
    }
}
