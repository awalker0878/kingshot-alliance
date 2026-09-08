<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\MultiFactorAuthentication\Providers;

use App\Contexts\Accounts\MultiFactorAuthentication\Services\MfaLoginChallenge;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class MultiFactorAuthenticationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('two-factor-challenge', static fn (Request $request): Limit => Limit::perMinute(5)->by(
            (string) (app(MfaLoginChallenge::class)->pending($request)['user_id'] ?? 'guest').'|'.(string) $request->ip(),
        ));
    }
}
