<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Providers;

use App\Contexts\Platform\DataGovernance\Console\Commands\EnforcePlatformRetentionCommand;
use App\Contexts\Platform\DataGovernance\Console\Commands\ProcessAccountDeletionsCommand;
use Illuminate\Support\ServiceProvider;

final class DataGovernanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                EnforcePlatformRetentionCommand::class,
                ProcessAccountDeletionsCommand::class,
            ]);
        }
    }
}
