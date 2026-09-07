<?php

declare(strict_types=1);

namespace App\ReadModels\CommandOverview\Providers;

use App\ReadModels\CommandOverview\Console\Commands\QueueOfficerBriefsCommand;
use Illuminate\Support\ServiceProvider;

final class CommandOverviewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([QueueOfficerBriefsCommand::class]);
        }
    }
}
