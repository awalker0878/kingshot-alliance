<?php

declare(strict_types=1);

namespace App\ReadModels\TerritoryPlanning\Providers;

use App\ReadModels\TerritoryPlanning\Http\Controllers\TerritoryPlanningPageController;
use App\ReadModels\TerritoryPlanning\Http\Controllers\TerritoryReconciliationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class TerritoryPlanningReadModelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'auth.session', 'verified'])->group(function (): void {
            Route::get('/territory', [TerritoryPlanningPageController::class, 'index'])->name('territory.index');
            Route::get('/territory/{plan}/alliances', [TerritoryPlanningPageController::class, 'alliances'])->whereUlid('plan')->name('territory.alliances');
            Route::get('/territory/{plan}/reconciliation', [TerritoryReconciliationController::class, 'show'])->whereUlid('plan')->name('territory.reconciliation');
            Route::get('/territory/{plan}', [TerritoryPlanningPageController::class, 'show'])->whereUlid('plan')->name('territory.show');
        });
    }
}
