<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Providers;

use App\Contexts\Intelligence\Ingestion\Console\Commands\EnforceKingdomIngestionRetentionCommand;
use App\Contexts\Intelligence\Ingestion\Console\Commands\KingdomIngestionHealthCommand;
use App\Contexts\Intelligence\Ingestion\Console\Commands\QueueKingdomIngestionCommand;
use App\Contexts\Intelligence\Ingestion\Console\Commands\ReconcileKingdomIngestionSourcesCommand;
use Illuminate\Support\ServiceProvider;

final class IngestionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                EnforceKingdomIngestionRetentionCommand::class,
                KingdomIngestionHealthCommand::class,
                QueueKingdomIngestionCommand::class,
                ReconcileKingdomIngestionSourcesCommand::class,
            ]);
        }
    }
}
