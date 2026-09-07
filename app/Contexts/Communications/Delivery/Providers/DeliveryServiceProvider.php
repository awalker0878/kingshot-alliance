<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Providers;

use App\Contexts\Communications\Delivery\Console\Commands\BuildNotificationDigestsCommand;
use App\Contexts\Communications\Delivery\Console\Commands\ProcessNotificationDeliveriesCommand;
use App\Contexts\Communications\Delivery\Console\Commands\ProcessNotificationDigestsCommand;
use App\Contexts\Communications\Delivery\Console\Commands\QueueIntelligenceChangesCommand;
use App\Contexts\Communications\Delivery\Console\Commands\QueueOfficerBriefsCommand;
use Illuminate\Support\ServiceProvider;

final class DeliveryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildNotificationDigestsCommand::class,
                ProcessNotificationDeliveriesCommand::class,
                ProcessNotificationDigestsCommand::class,
                QueueIntelligenceChangesCommand::class,
                QueueOfficerBriefsCommand::class,
            ]);
        }
    }
}
