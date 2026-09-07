<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Providers;

use App\Contexts\Intelligence\Sharing\Console\Commands\EnforceKingdomIntelligenceSharingRetentionCommand;
use Illuminate\Support\ServiceProvider;

final class SharingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                EnforceKingdomIntelligenceSharingRetentionCommand::class,
            ]);
        }
    }
}
