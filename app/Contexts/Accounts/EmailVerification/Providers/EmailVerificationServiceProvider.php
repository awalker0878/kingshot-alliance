<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Providers;

use App\Contexts\Accounts\EmailVerification\Actions\SendRequestedEmailVerification;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class EmailVerificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OutboxPublished::class, static function (OutboxPublished $event): void {
            app(SendRequestedEmailVerification::class)->handle($event);
        });
    }
}
