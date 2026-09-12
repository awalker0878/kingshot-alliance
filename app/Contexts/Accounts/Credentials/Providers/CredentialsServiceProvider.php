<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Providers;

use App\Contexts\Accounts\Credentials\Actions\SendRequestedPasswordReset;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class CredentialsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OutboxPublished::class, static function (OutboxPublished $event): void {
            app(SendRequestedPasswordReset::class)->handle($event);
        });
    }
}
