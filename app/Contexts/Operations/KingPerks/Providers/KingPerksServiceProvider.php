<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Providers;

use App\Contexts\Operations\KingPerks\Reminders\Actions\MarkKingPerkReminderSent;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class KingPerksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OutboxPublished::class, function (OutboxPublished $event): void {
            $this->app->make(MarkKingPerkReminderSent::class)->handle($event);
        });
    }
}
