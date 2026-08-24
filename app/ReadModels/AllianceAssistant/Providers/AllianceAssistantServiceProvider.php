<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AllianceAssistantServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('alliance-assistant', static function (Request $request): Limit {
            $limit = max(1, (int) config('assistant.rate_limit_per_minute', 30));
            $accountId = $request->user()?->getAuthIdentifier();
            $key = $accountId === null ? (string) $request->ip() : 'account:'.(string) $accountId;

            return Limit::perMinute($limit)->by($key);
        });
    }
}
