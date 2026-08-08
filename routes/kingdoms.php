<?php

declare(strict_types=1);

use App\Domain\Kingdoms\Http\Controllers\KingdomSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'alliance.context'])->group(function (): void {
    Route::get('/alliance/settings/kingdom', [KingdomSettingsController::class, 'index'])
        ->name('alliance.kingdom.edit');

    Route::patch('/alliance/settings/kingdom', [KingdomSettingsController::class, 'update'])
        ->middleware('password.confirm')
        ->name('alliance.kingdom.update');
});
