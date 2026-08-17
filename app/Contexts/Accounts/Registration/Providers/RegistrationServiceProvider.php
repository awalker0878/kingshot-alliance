<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Registration\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class RegistrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('registration', static fn (Request $request): Limit => Limit::perMinute(3)->by(
            Str::lower(trim((string) $request->input('email'))).'|'.(string) $request->ip(),
        ));
    }
}
