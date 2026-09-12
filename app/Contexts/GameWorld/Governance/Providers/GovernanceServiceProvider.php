<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Providers;

use App\Contexts\GameWorld\Governance\Console\Commands\ExpireKingdomRoleAssignmentsCommand;
use Illuminate\Support\ServiceProvider;

final class GovernanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ExpireKingdomRoleAssignmentsCommand::class]);
        }
    }
}
