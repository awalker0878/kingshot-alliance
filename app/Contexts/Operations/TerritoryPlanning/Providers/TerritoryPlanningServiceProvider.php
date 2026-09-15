<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Providers;

use App\Contexts\Operations\TerritoryPlanning\Console\Commands\PruneTerritoryRecoveryDraftsCommand;
use App\Contexts\Operations\TerritoryPlanning\Http\Controllers\TerritoryArtifactController;
use App\Contexts\Operations\TerritoryPlanning\Http\Controllers\TerritoryCollaborationController;
use App\Contexts\Operations\TerritoryPlanning\Http\Controllers\TerritoryPlanController;
use App\Contexts\Operations\TerritoryPlanning\Http\Controllers\TerritoryWorkspaceViewController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class TerritoryPlanningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PruneTerritoryRecoveryDraftsCommand::class]);
        }

        Route::middleware(['web', 'auth', 'auth.session', 'verified'])->group(function (): void {
            Route::get('/territory/workspace-views', [TerritoryWorkspaceViewController::class, 'show'])->name('territory.workspace-views.show');
            Route::put('/territory/workspace-views', [TerritoryWorkspaceViewController::class, 'update'])->name('territory.workspace-views.update');
            Route::get('/territory/{plan}/collaboration', [TerritoryCollaborationController::class, 'overview'])->whereUlid('plan')->name('territory.collaboration');
            Route::post('/territory/{plan}/comments', [TerritoryCollaborationController::class, 'comment'])->whereUlid('plan')->name('territory.comments.store');
            Route::post('/territory/{plan}/reviews', [TerritoryCollaborationController::class, 'review'])->whereUlid('plan')->name('territory.reviews.store');
            Route::post('/territory/{plan}/access', [TerritoryCollaborationController::class, 'grant'])->whereUlid('plan')->name('territory.access.store');
            Route::delete('/territory/{plan}/access/{grant}', [TerritoryCollaborationController::class, 'revokeGrant'])->whereUlid('plan')->whereUlid('grant')->name('territory.access.destroy');
            Route::post('/territory/{plan}/shares', [TerritoryCollaborationController::class, 'share'])->whereUlid('plan')->name('territory.shares.store');
            Route::delete('/territory/{plan}/shares/{share}', [TerritoryCollaborationController::class, 'revokeShare'])->whereUlid('plan')->whereUlid('share')->name('territory.shares.destroy');
            Route::get('/territory/shared/{share}', [TerritoryCollaborationController::class, 'viewer'])->whereUlid('share')->name('territory.shared.viewer');
            Route::post('/territory/shared/{share}', [TerritoryCollaborationController::class, 'shared'])->whereUlid('share')->name('territory.shared');
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
            Route::get('/territory/{plan}/recovery', [TerritoryPlanController::class, 'recovery'])
                ->whereUlid('plan')
                ->name('territory.recovery.show');
            Route::put('/territory/{plan}/recovery', [TerritoryPlanController::class, 'saveRecovery'])
                ->whereUlid('plan')
                ->name('territory.recovery.update');
            Route::delete('/territory/{plan}/recovery', [TerritoryPlanController::class, 'discardRecovery'])
                ->whereUlid('plan')
                ->name('territory.recovery.destroy');
            Route::put('/territory/{plan}', [TerritoryPlanController::class, 'save'])
                ->whereUlid('plan')
                ->name('territory.save');
            Route::put('/territory/{plan}/annotations', [TerritoryArtifactController::class, 'annotations'])
                ->whereUlid('plan')
                ->name('territory.annotations.update');
            Route::post('/territory/{plan}/hive-templates', [TerritoryArtifactController::class, 'saveTemplate'])
                ->whereUlid('plan')
                ->name('territory.hive-templates.store');
            Route::post('/territory/{plan}/hive-templates/{template}/instantiate', [TerritoryArtifactController::class, 'instantiateTemplate'])
                ->whereUlid('plan')
                ->whereUlid('template')
                ->name('territory.hive-templates.instantiate');
            Route::post('/territory/{plan}/renditions', [TerritoryArtifactController::class, 'rendition'])
                ->whereUlid('plan')
                ->name('territory.renditions.store');
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
