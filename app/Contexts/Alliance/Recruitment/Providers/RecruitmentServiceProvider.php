<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Providers;

use App\Contexts\Alliance\Recruitment\Actions\MarkRecruitmentCandidateJoined;
use App\Contexts\Alliance\Recruitment\Http\Controllers\RecruitmentReentryController;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class RecruitmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('recruitment-application', static fn (Request $request): Limit => Limit::perMinute(3)->by(
            Str::lower(trim((string) $request->input('email'))).'|'.(string) $request->ip(),
        ));

        Route::middleware(['web', 'auth', 'auth.session', 'verified', 'alliance.context'])
            ->group(function (): void {
                Route::get('/alliance/recruitment/{candidate}/reentry', [RecruitmentReentryController::class, 'show'])
                    ->whereUlid('candidate')
                    ->name('alliance.recruitment.reentry.show');
                Route::patch('/alliance/recruitment/{candidate}/reentry', [RecruitmentReentryController::class, 'update'])
                    ->whereUlid('candidate')
                    ->middleware('password.confirm')
                    ->name('alliance.recruitment.reentry.update');
            });

        Event::listen(OutboxPublished::class, function (OutboxPublished $event): void {
            $this->app->make(MarkRecruitmentCandidateJoined::class)->handle($event);
        });
    }
}
