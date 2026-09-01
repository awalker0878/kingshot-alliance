<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class AuthenticationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('login', static fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::lower(trim((string) $request->input('email'))).'|'.(string) $request->ip(),
        ));

        RateLimiter::for('google-auth', static fn (Request $request): Limit => Limit::perMinute(10)->by(
            (string) $request->ip(),
        ));

        $this->loadRoutesFrom(base_path('routes/auth.php'));
    }
}
