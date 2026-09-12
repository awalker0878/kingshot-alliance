<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Providers;

use App\Contexts\Accounts\EmailVerification\Actions\SendRequestedEmailVerification;
use App\Contexts\Accounts\EmailVerification\Services\EmailChangedNoticeOutbox;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class EmailVerificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('account-email-change', static fn (Request $request): Limit => Limit::perMinute(6)->by(
            (string) $request->user()?->getAuthIdentifier(),
        ));

        Event::listen(OutboxPublished::class, static function (OutboxPublished $event): void {
            app(SendRequestedEmailVerification::class)->handle($event);
            app(EmailChangedNoticeOutbox::class)->deliver($event);
        });
    }
}
