<?php

declare(strict_types=1);

use App\Contexts\GameWorld\Progression\Http\Controllers\ProgressionLibraryController;
use App\Contexts\Intelligence\Evidence\Http\Controllers\GovernorProgressionEvidenceController;
use App\ReadModels\Progression\Http\Controllers\GovernorProgressionController;
use App\ReadModels\Progression\Http\Controllers\ProgressionPlannerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/progression', ProgressionLibraryController::class)
        ->name('progression.index');
    Route::get('/progression/governor', GovernorProgressionController::class)
        ->name('progression.governor');
    Route::get('/progression/governor/planner', ProgressionPlannerController::class)
        ->name('progression.governor.planner');
    Route::get('/progression/governor/{entry}/evidence', [GovernorProgressionEvidenceController::class, 'index'])
        ->name('progression.governor.evidence.index');
    Route::get('/progression/governor/{entry}/evidence/{evidence}/image', [GovernorProgressionEvidenceController::class, 'image'])
        ->name('progression.governor.evidence.image');
    Route::get('/progression/governor/{entry}/evidence/reviews/{review}/preview', [GovernorProgressionEvidenceController::class, 'preview'])
        ->name('progression.governor.evidence.preview');

    Route::middleware('password.confirm')->group(function (): void {
        Route::post('/progression/governor/{entry}/evidence', [GovernorProgressionEvidenceController::class, 'store'])
            ->name('progression.governor.evidence.store');
        Route::post('/progression/governor/{entry}/evidence/{evidence}/review', [GovernorProgressionEvidenceController::class, 'review'])
            ->name('progression.governor.evidence.review');
        Route::post('/progression/governor/{entry}/evidence/reviews/{review}/resolve-duplicate', [GovernorProgressionEvidenceController::class, 'resolveDuplicate'])
            ->name('progression.governor.evidence.resolve-duplicate');
        Route::post('/progression/governor/{entry}/evidence/reviews/{review}/commit', [GovernorProgressionEvidenceController::class, 'commit'])
            ->name('progression.governor.evidence.commit');
        Route::post('/progression/governor/{entry}/evidence/{evidence}/retry', [GovernorProgressionEvidenceController::class, 'retry'])
            ->name('progression.governor.evidence.retry');
        Route::delete('/progression/governor/{entry}/evidence/{evidence}', [GovernorProgressionEvidenceController::class, 'destroy'])
            ->name('progression.governor.evidence.destroy');
    });
});
