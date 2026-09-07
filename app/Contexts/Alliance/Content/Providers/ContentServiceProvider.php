<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Providers;

use App\Contexts\Alliance\Content\Console\Commands\PublishScheduledContentCommand;
use App\Contexts\Alliance\Content\Console\Commands\QueueAnnouncementBroadcastsCommand;
use Illuminate\Support\ServiceProvider;

final class ContentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishScheduledContentCommand::class,
                QueueAnnouncementBroadcastsCommand::class,
            ]);
        }
    }
}
