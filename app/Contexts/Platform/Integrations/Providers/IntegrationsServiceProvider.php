<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Providers;

use App\Contexts\Platform\Integrations\Actions\QueueWebhookDeliveries;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class IntegrationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(120)
            ->by('api-client:'.($request->ip() ?? 'unknown')));

        Event::listen(OutboxPublished::class, function (OutboxPublished $event): void {
            $this->app->make(QueueWebhookDeliveries::class)->handle($event);
        });
    }
}
