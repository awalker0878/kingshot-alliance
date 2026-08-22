<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Providers;

use App\Contexts\Operations\TerritoryPlanning\Http\Controllers\TerritoryPlanController;
use App\ReadModels\TerritoryPlanning\Http\Controllers\TerritoryPlanningPageController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class TerritoryPlanningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'auth.session', 'verified'])->group(function (): void {
            Route::get('/territory', [TerritoryPlanningPageController::class, 'index'])->name('territory.index');
            Route::get('/territory/{plan}', [TerritoryPlanningPageController::class, 'show'])->whereUlid('plan')->name('territory.show');
            Route::post('/territory/hive-preview', [TerritoryPlanController::class, 'generateHive'])->name('territory.hive-preview');
            Route::post('/territory/import-preview', [TerritoryPlanController::class, 'previewImport'])->name('territory.import-preview');
            Route::put('/territory/{plan}', [TerritoryPlanController::class, 'save'])->whereUlid('plan')->name('territory.save');

            Route::middleware('password.confirm')->group(function (): void {
                Route::post('/territory', [TerritoryPlanController::class, 'store'])->name('territory.store');
                Route::post('/territory/{plan}/publish', [TerritoryPlanController::class, 'publish'])->whereUlid('plan')->name('territory.publish');
                Route::delete('/territory/{plan}', [TerritoryPlanController::class, 'archive'])->whereUlid('plan')->name('territory.archive');
                Route::post('/territory/{plan}/revisions/{revision}/restore', [TerritoryPlanController::class, 'restore'])->whereUlid('plan')->whereUlid('revision')->name('territory.revisions.restore');
            });
        });
    }
}
