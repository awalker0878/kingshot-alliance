<?php

declare(strict_types=1);

use App\Contexts\Accounts\Authentication\Http\Controllers\AccountSessionController;
use App\Contexts\Accounts\Credentials\Http\Controllers\PasswordSignInMethodController;
use App\Contexts\Accounts\Profile\Http\Controllers\AccountDeletionController;
use App\Contexts\Accounts\Profile\Http\Controllers\EmailChangeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('/profile/delete-account', [AccountDeletionController::class, 'show'])
        ->name('profile.delete-account.show');

    Route::prefix('profile/security')
        ->name('profile.security.')
        ->group(function (): void {
            Route::get('/email/verify/{id}/{hash}', [EmailChangeController::class, 'verify'])
                ->middleware(['signed', 'throttle:6,1'])
                ->whereNumber('id')
                ->name('email.verify');
        });
});

Route::middleware(['auth', 'auth.session', 'verified', 'password.confirm'])->group(function (): void {
    Route::post('/profile/delete-account', [AccountDeletionController::class, 'store'])
        ->name('profile.delete-account.store');
    Route::delete('/profile/delete-account', [AccountDeletionController::class, 'destroy'])
        ->name('profile.delete-account.cancel');

    Route::prefix('profile/security')
        ->name('profile.security.')
        ->group(function (): void {
            Route::patch('/email', [EmailChangeController::class, 'update'])
                ->name('email.update');
            Route::post('/password', [PasswordSignInMethodController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('password.store');
            Route::delete('/password', [PasswordSignInMethodController::class, 'destroy'])
                ->middleware('throttle:6,1')
                ->name('password.destroy');
            Route::delete('/sessions/{session}', [AccountSessionController::class, 'destroy'])
                ->whereUuid('session')
                ->name('sessions.destroy');
            Route::delete('/sessions', [AccountSessionController::class, 'destroyOthers'])
                ->name('sessions.destroy-others');
        });
});
