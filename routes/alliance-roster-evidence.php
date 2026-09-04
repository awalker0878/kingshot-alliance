<?php

declare(strict_types=1);

use App\Contexts\Intelligence\Evidence\Http\Controllers\AllianceRosterEvidenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'auth.session', 'verified', 'alliance.context'])->group(function (): void {
    Route::get('/alliance/roster/evidence', [AllianceRosterEvidenceController::class, 'index'])
        ->name('alliance.roster.evidence.index');
    Route::post('/alliance/roster/evidence', [AllianceRosterEvidenceController::class, 'upload'])
        ->name('alliance.roster.evidence.upload');
    Route::post('/alliance/roster/evidence/{evidence}/review', [AllianceRosterEvidenceController::class, 'review'])
        ->whereUlid('evidence')
        ->name('alliance.roster.evidence.review');
    Route::post('/alliance/roster/evidence/reviews/{review}/commit', [AllianceRosterEvidenceController::class, 'commit'])
        ->whereUlid('review')
        ->middleware('password.confirm')
        ->name('alliance.roster.evidence.commit');
});
