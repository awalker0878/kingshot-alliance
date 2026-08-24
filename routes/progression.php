<?php

declare(strict_types=1);

use App\Contexts\GameWorld\Progression\Http\Controllers\ProgressionLibraryController;
use App\ReadModels\Progression\Http\Controllers\GovernorProgressionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/progression', ProgressionLibraryController::class)
        ->name('progression.index');
    Route::get('/progression/governor', GovernorProgressionController::class)
        ->name('progression.governor');
});
