<?php

declare(strict_types=1);

namespace App\Shared\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $trustedProxies = trim((string) config('operations.trusted_proxies', ''));

        if ($trustedProxies === '') {
            return;
        }

        TrustProxies::at($trustedProxies);
        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );
    }
}
