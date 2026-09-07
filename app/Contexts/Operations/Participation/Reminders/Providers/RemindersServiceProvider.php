<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Providers;

use App\Contexts\Operations\Participation\Reminders\Console\Commands\QueueEventRemindersCommand;
use Illuminate\Support\ServiceProvider;

final class RemindersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                QueueEventRemindersCommand::class,
            ]);
        }
    }
}
