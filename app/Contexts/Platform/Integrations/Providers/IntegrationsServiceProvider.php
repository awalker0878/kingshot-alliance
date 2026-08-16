<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Providers;

use App\Contexts\Platform\Integrations\Actions\QueueWebhookDeliveries;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class IntegrationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OutboxPublished::class, function (OutboxPublished $event): void {
            $this->app->make(QueueWebhookDeliveries::class)->handle($event);
        });
    }
}
