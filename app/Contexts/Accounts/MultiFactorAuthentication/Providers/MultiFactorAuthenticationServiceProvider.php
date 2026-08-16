<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\MultiFactorAuthentication\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class MultiFactorAuthenticationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('two-factor-challenge', static fn (Request $request): Limit => Limit::perMinute(5)->by(
            (string) $request->session()->get('accounts.two_factor_challenge_user_id', 'guest').'|'.(string) $request->ip(),
        ));
    }
}
