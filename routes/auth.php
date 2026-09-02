<?php

declare(strict_types=1);

use App\Workflows\AccountOnboarding\Http\Controllers\GoogleAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])->group(function (): void {
    Route::get('/auth/google', [GoogleAuthenticationController::class, 'redirect'])
        ->middleware('throttle:google-auth')
        ->name('auth.google.redirect');
});

Route::middleware('web')->group(function (): void {
    Route::get('/auth/google/callback', [GoogleAuthenticationController::class, 'callback'])
        ->middleware('throttle:google-auth')
        ->name('auth.google.callback');

    Route::get('/auth/google/reauthenticate', [GoogleAuthenticationController::class, 'reauthenticate'])
        ->middleware(['auth', 'auth.session', 'throttle:google-auth'])
        ->name('auth.google.reauthenticate');

    Route::middleware(['auth', 'auth.session', 'verified', 'password.confirm'])->group(function (): void {
        Route::get('/auth/google/connect', [GoogleAuthenticationController::class, 'connect'])
            ->middleware('throttle:google-auth')
            ->name('auth.google.connect');
        Route::delete('/profile/security/google', [GoogleAuthenticationController::class, 'disconnect'])
            ->middleware('throttle:google-auth')
            ->name('profile.security.google.disconnect');
    });
});
