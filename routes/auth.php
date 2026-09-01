<?php

declare(strict_types=1);

use App\Contexts\Accounts\Authentication\Http\Controllers\GoogleAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])->group(function (): void {
    Route::get('/auth/google', [GoogleAuthenticationController::class, 'redirect'])
        ->name('auth.google.redirect');

    Route::get('/auth/google/callback', [GoogleAuthenticationController::class, 'callback'])
        ->middleware('throttle:google-auth')
        ->name('auth.google.callback');
});
