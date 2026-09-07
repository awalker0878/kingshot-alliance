<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Providers;

use App\Contexts\GameWorld\Kingdoms\Console\Commands\BootstrapKingdomAdministratorCommand;
use Illuminate\Support\ServiceProvider;

final class KingdomsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BootstrapKingdomAdministratorCommand::class,
            ]);
        }
    }
}
