<?php

declare(strict_types=1);

namespace App\ReadModels\IntelligenceSignals\Providers;

use App\ReadModels\IntelligenceSignals\Console\Commands\QueueIntelligenceChangesCommand;
use Illuminate\Support\ServiceProvider;

final class IntelligenceSignalsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([QueueIntelligenceChangesCommand::class]);
        }
    }
}
