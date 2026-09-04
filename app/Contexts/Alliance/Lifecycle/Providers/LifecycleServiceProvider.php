<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Providers;

use App\Contexts\Alliance\Lifecycle\Http\Controllers\AllianceSettingsController;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class LifecycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AllianceContext::class);
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'auth.session', 'verified', 'alliance.context'])
            ->group(function (): void {
                Route::get('/alliance/settings', [AllianceSettingsController::class, 'index'])
                    ->name('alliance.settings.index');
                Route::patch('/alliance/settings', [AllianceSettingsController::class, 'update'])
                    ->middleware('password.confirm')
                    ->name('alliance.settings.update');
            });
    }
}
