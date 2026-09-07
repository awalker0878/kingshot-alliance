<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Providers;

use App\Contexts\GameWorld\KingdomMaps\Console\Commands\KingdomMapsDiffCommand;
use App\Contexts\GameWorld\KingdomMaps\Console\Commands\KingdomMapsListCommand;
use App\Contexts\GameWorld\KingdomMaps\Console\Commands\KingdomMapsValidateCommand;
use App\Contexts\GameWorld\KingdomMaps\Console\Commands\KingdomMapsVerifySourcesCommand;
use Illuminate\Support\ServiceProvider;

final class KingdomMapsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                KingdomMapsListCommand::class,
                KingdomMapsValidateCommand::class,
                KingdomMapsVerifySourcesCommand::class,
                KingdomMapsDiffCommand::class,
            ]);
        }
    }
}
