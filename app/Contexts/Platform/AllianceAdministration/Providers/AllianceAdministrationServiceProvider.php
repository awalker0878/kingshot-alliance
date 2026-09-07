<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Providers;

use App\Contexts\Platform\AllianceAdministration\Console\Commands\CapturePlatformUsageCommand;
use Illuminate\Support\ServiceProvider;

final class AllianceAdministrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CapturePlatformUsageCommand::class,
            ]);
        }
    }
}
