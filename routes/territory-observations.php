<?php

declare(strict_types=1);

use App\Contexts\Intelligence\Evidence\Http\Controllers\TerritorySpatialEvidenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'auth.session', 'verified', 'alliance.context'])->group(function (): void {
    Route::get('/territory-observations/evidence/{evidence}/image', [TerritorySpatialEvidenceController::class, 'image'])
        ->whereUlid('evidence')
        ->name('territory-observations.evidence.image');

    Route::middleware('password.confirm')->group(function (): void {
        Route::post('/territory-observations/evidence', [TerritorySpatialEvidenceController::class, 'store'])
            ->name('territory-observations.evidence.store');
        Route::post('/territory-observations/evidence/{evidence}/review', [TerritorySpatialEvidenceController::class, 'review'])
            ->whereUlid('evidence')
            ->name('territory-observations.evidence.review');
        Route::post('/territory-observations/reviews/{review}/resolve-duplicate', [TerritorySpatialEvidenceController::class, 'resolveDuplicate'])
            ->whereUlid('review')
            ->name('territory-observations.evidence.resolve-duplicate');
        Route::post('/territory-observations/reviews/{review}/commit', [TerritorySpatialEvidenceController::class, 'commit'])
            ->whereUlid('review')
            ->name('territory-observations.evidence.commit');
        Route::post('/territory-observations/evidence/{evidence}/retry', [TerritorySpatialEvidenceController::class, 'retry'])
            ->whereUlid('evidence')
            ->name('territory-observations.evidence.retry');
        Route::delete('/territory-observations/evidence/{evidence}', [TerritorySpatialEvidenceController::class, 'destroy'])
            ->whereUlid('evidence')
            ->name('territory-observations.evidence.destroy');
        Route::post('/territory-observations/{observation}/invalidate', [TerritorySpatialEvidenceController::class, 'invalidate'])
            ->whereUlid('observation')
            ->name('territory-observations.invalidate');
    });
});
