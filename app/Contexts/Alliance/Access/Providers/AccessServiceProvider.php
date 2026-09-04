<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Providers;

use App\Contexts\Alliance\Access\Http\Controllers\AllianceRoleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'auth.session', 'verified', 'alliance.context'])
            ->group(function (): void {
                Route::get('/alliance/roles', [AllianceRoleController::class, 'index'])
                    ->name('alliance.roles.index');
                Route::middleware('password.confirm')->group(function (): void {
                    Route::post('/alliance/roles', [AllianceRoleController::class, 'store'])
                        ->name('alliance.roles.store');
                    Route::patch('/alliance/roles/{role}', [AllianceRoleController::class, 'update'])
                        ->whereUlid('role')
                        ->name('alliance.roles.update');
                    Route::delete('/alliance/roles/{role}', [AllianceRoleController::class, 'destroy'])
                        ->whereUlid('role')
                        ->name('alliance.roles.destroy');
                });
            });
    }
}
