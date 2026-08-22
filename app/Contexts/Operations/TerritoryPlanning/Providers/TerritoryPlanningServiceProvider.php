<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Providers;

use App\Contexts\Operations\TerritoryPlanning\Http\Controllers\TerritoryPlanController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class TerritoryPlanningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'auth.session', 'verified'])->group(function (): void {
            Route::get('/territory/{plan}/revisions/{revision}', [TerritoryPlanController::class, 'revision'])
                ->whereUlid('plan')
                ->whereUlid('revision')
                ->name('territory.revisions.show');
            Route::post('/territory/hive-preview', [TerritoryPlanController::class, 'generateHive'])
                ->name('territory.hive-preview');
            Route::post('/territory/import-preview', [TerritoryPlanController::class, 'previewImport'])
                ->name('territory.import-preview');
            Route::post('/territory', [TerritoryPlanController::class, 'store'])
                ->name('territory.store');
            Route::put('/territory/{plan}/alliances', [TerritoryPlanController::class, 'updateAlliances'])
                ->whereUlid('plan')
                ->name('territory.alliances.update');
            Route::put('/territory/{plan}', [TerritoryPlanController::class, 'save'])
                ->whereUlid('plan')
                ->name('territory.save');
            Route::post('/territory/{plan}/import', [TerritoryPlanController::class, 'import'])
                ->whereUlid('plan')
                ->name('territory.import');
            Route::post('/territory/{plan}/publish', [TerritoryPlanController::class, 'publish'])
                ->whereUlid('plan')
                ->name('territory.publish');
            Route::delete('/territory/{plan}', [TerritoryPlanController::class, 'archive'])
                ->whereUlid('plan')
                ->name('territory.archive');
            Route::post('/territory/{plan}/clone', [TerritoryPlanController::class, 'clone'])
                ->whereUlid('plan')
                ->name('territory.clone');
            Route::post('/territory/{plan}/revisions/{revision}/restore', [TerritoryPlanController::class, 'restore'])
                ->whereUlid('plan')
                ->whereUlid('revision')
                ->name('territory.revisions.restore');
            Route::put('/events/{occurrence}/territory-positioning', [TerritoryPlanController::class, 'attachEvent'])
                ->whereUlid('occurrence')
                ->name('events.territory-positioning.update');
            Route::delete('/events/{occurrence}/territory-positioning', [TerritoryPlanController::class, 'detachEvent'])
                ->whereUlid('occurrence')
                ->name('events.territory-positioning.destroy');
        });
    }
}
