<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Providers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use Illuminate\Support\ServiceProvider;

final class LifecycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AllianceContext::class);
    }
}
