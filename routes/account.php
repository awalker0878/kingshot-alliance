<?php

declare(strict_types=1);

use App\Domain\Identity\Http\Controllers\AccountDeletionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/profile/delete-account', [AccountDeletionController::class, 'show'])
        ->name('profile.deletion.show');
    Route::post('/profile/delete-account', [AccountDeletionController::class, 'store'])
        ->middleware('password.confirm')
        ->name('profile.deletion.store');
});
