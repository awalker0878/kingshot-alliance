<?php

declare(strict_types=1);

namespace App\Workflows\NotificationDelivery\Providers;

use App\Contexts\Communications\Delivery\Contracts\NotificationSourceAuthorization;
use App\Workflows\NotificationDelivery\Console\Commands\QueueIntelligenceChangesCommand;
use App\Workflows\NotificationDelivery\Console\Commands\QueueOfficerBriefsCommand;
use App\Workflows\NotificationDelivery\Console\Commands\QueueTerritoryNotificationsCommand;
use App\Workflows\NotificationDelivery\Services\CurrentNotificationSourceAuthorization;
use Illuminate\Support\ServiceProvider;

final class NotificationDeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationSourceAuthorization::class, CurrentNotificationSourceAuthorization::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([QueueOfficerBriefsCommand::class, QueueIntelligenceChangesCommand::class, QueueTerritoryNotificationsCommand::class]);
        }
    }
}
