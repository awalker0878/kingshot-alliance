<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Providers;

use App\Contexts\Operations\Participation\Reminders\Actions\MarkEventReminderSent;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ParticipationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OutboxPublished::class, function (OutboxPublished $event): void {
            $this->app->make(MarkEventReminderSent::class)->handle($event);
        });
    }
}
