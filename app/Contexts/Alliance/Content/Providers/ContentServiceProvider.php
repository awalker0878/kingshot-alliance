<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Providers;

use App\Contexts\Alliance\Content\Services\BasicMediaScanner;
use App\Contexts\Alliance\Content\Services\MediaScanner;
use Illuminate\Support\ServiceProvider;

final class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MediaScanner::class, BasicMediaScanner::class);
    }
}
