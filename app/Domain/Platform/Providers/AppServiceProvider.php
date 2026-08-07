<?php

declare(strict_types=1);

namespace App\Domain\Platform\Providers;

use App\Domain\Content\Services\BasicMediaScanner;
use App\Domain\Content\Services\MediaScanner;
use App\Domain\Notifications\Actions\MarkEventReminderPublished;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Recruitment\Actions\MarkRecruitmentCandidateJoined;
use App\Domain\Platform\Events\OutboxPublished;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        $this->app->bind(MediaScanner::class, BasicMediaScanner::class);

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

        Event::listen(OutboxPublished::class, function (OutboxPublished $event): void {
            $this->app->make(MarkEventReminderPublished::class)->handle($event);
            $this->app->make(MarkRecruitmentCandidateJoined::class)->handle($event);
        });

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
            static fn (Request $request): Limit => Limit::perMinute(3)->by(
                Str::lower(trim((string) $request->input('email'))).'|'.(string) $request->ip(),
            ),
        );

        RateLimiter::for(
            'recruitment-application',
            static fn (Request $request): Limit => Limit::perMinute(3)->by(
                Str::lower(trim((string) $request->input('email'))).'|'.(string) $request->ip(),
            ),
        );

        RateLimiter::for(
            'two-factor-challenge',
            static fn (Request $request): Limit => Limit::perMinute(5)->by(
                (string) $request->session()->get('identity.two_factor_challenge_user_id', 'guest')
                .'|'.(string) $request->ip(),
            ),
        );
    }
}
