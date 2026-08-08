<?php

declare(strict_types=1);

use App\Domain\Kingdoms\Http\Controllers\KingdomSettingsController;
use App\Domain\Kingdoms\Http\Controllers\RosterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'alliance.context'])->group(function (): void {
    Route::get('/alliance/settings/kingdom', [KingdomSettingsController::class, 'index'])
        ->name('alliance.kingdom.edit');

    Route::patch('/alliance/settings/kingdom', [KingdomSettingsController::class, 'update'])
        ->middleware('password.confirm')
        ->name('alliance.kingdom.update');

    Route::get('/alliance/roster', [RosterController::class, 'index'])
        ->name('alliance.roster.index');

    Route::get('/alliance/roster/manage', [RosterController::class, 'manage'])
        ->name('alliance.roster.manage');

    Route::middleware('password.confirm')->group(function (): void {
        Route::post('/alliance/roster', [RosterController::class, 'store'])
            ->name('alliance.roster.store');
        Route::patch('/alliance/roster/{entry}', [RosterController::class, 'update'])
            ->name('alliance.roster.update');
        Route::post('/alliance/roster/{entry}/leave', [RosterController::class, 'leave'])
            ->name('alliance.roster.leave');
    });
});
