<?php

declare(strict_types=1);

namespace App\Workflows\KingdomGovernance\Providers;

use App\Workflows\KingdomGovernance\Console\Commands\BootstrapKingdomAdministratorCommand;
use Illuminate\Support\ServiceProvider;

final class KingdomGovernanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapKingdomAdministratorCommand::class]);
        }
    }
}
