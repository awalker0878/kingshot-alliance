<?php

declare(strict_types=1);

use App\Contexts\GameWorld\Players\Http\Controllers\GovernorManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/governors', [GovernorManagementController::class, 'index'])
        ->name('governors.index');
    Route::post('/governors', [GovernorManagementController::class, 'store'])
        ->name('governors.store');
    Route::patch('/governors/{player}', [GovernorManagementController::class, 'update'])
        ->whereUlid('player')
        ->name('governors.update');
    Route::post('/governors/{player}/move', [GovernorManagementController::class, 'move'])
        ->whereUlid('player')
        ->name('governors.move');
    Route::delete('/governors/{player}', [GovernorManagementController::class, 'release'])
        ->whereUlid('player')
        ->middleware('password.confirm')
        ->name('governors.release');
});
