<?php

declare(strict_types=1);

use App\Contexts\Accounts\Authentication\Http\Controllers\AccountSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'password.confirm'])
    ->prefix('profile/security')
    ->name('profile.security.')
    ->group(function (): void {
        Route::delete('/sessions/{session}', [AccountSessionController::class, 'destroy'])
            ->whereUuid('session')
            ->name('sessions.destroy');
        Route::delete('/sessions', [AccountSessionController::class, 'destroyOthers'])
            ->name('sessions.destroy-others');
    });
