<?php

declare(strict_types=1);

namespace App\Workflows\NotificationDelivery\Providers;

use App\Workflows\NotificationDelivery\Console\Commands\QueueIntelligenceChangesCommand;
use App\Workflows\NotificationDelivery\Console\Commands\QueueOfficerBriefsCommand;
use Illuminate\Support\ServiceProvider;

final class NotificationDeliveryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([QueueOfficerBriefsCommand::class, QueueIntelligenceChangesCommand::class]);
        }
    }
}
