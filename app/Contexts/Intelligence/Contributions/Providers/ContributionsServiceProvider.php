<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Providers;

use App\Contexts\Intelligence\Contributions\Console\Commands\QueueContributionReportsCommand;
use Illuminate\Support\ServiceProvider;

final class ContributionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                QueueContributionReportsCommand::class,
            ]);
        }
    }
}
