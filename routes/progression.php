<?php

declare(strict_types=1);

use App\Contexts\GameWorld\Progression\Http\Controllers\ProgressionLibraryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])
    ->get('/progression', ProgressionLibraryController::class)
    ->name('progression.index');
