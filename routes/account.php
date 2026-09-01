<?php

declare(strict_types=1);

use App\Contexts\Accounts\Authentication\Http\Controllers\AccountSessionController;
use App\Contexts\Accounts\Profile\Http\Controllers\EmailChangeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session'])
    ->prefix('profile/security')
    ->name('profile.security.')
    ->group(function (): void {
        Route::get('/email/verify/{id}/{hash}', [EmailChangeController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->whereNumber('id')
            ->name('email.verify');
    });

Route::middleware(['auth', 'auth.session', 'verified', 'password.confirm'])
    ->prefix('profile/security')
    ->name('profile.security.')
    ->group(function (): void {
        Route::patch('/email', [EmailChangeController::class, 'update'])
            ->name('email.update');
        Route::delete('/sessions/{session}', [AccountSessionController::class, 'destroy'])
            ->whereUuid('session')
            ->name('sessions.destroy');
        Route::delete('/sessions', [AccountSessionController::class, 'destroyOthers'])
            ->name('sessions.destroy-others');
    });
