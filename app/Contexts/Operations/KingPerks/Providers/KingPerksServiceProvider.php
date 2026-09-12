<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Providers;

use App\Contexts\Operations\KingPerks\Console\Commands\QueueKingPerkRemindersCommand;
use Illuminate\Support\ServiceProvider;

final class KingPerksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([QueueKingPerkRemindersCommand::class]);
        }
    }
}
