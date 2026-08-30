<?php

declare(strict_types=1);

use App\Contexts\GameWorld\GiftCodes\Http\Controllers\GiftCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/gift-codes', [GiftCodeController::class, 'index'])
        ->name('gift-codes.index');
    Route::post('/gift-codes', [GiftCodeController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('gift-codes.store');
    Route::post('/gift-codes/{giftCode}/redeem', [GiftCodeController::class, 'redeem'])
        ->whereUlid('giftCode')
        ->middleware('throttle:30,1')
        ->name('gift-codes.redeem');
    Route::post('/gift-codes/{giftCode}/confirm', [GiftCodeController::class, 'confirm'])
        ->whereUlid('giftCode')
        ->name('gift-codes.confirm');
    Route::post('/gift-codes/{giftCode}/result', [GiftCodeController::class, 'result'])
        ->whereUlid('giftCode')
        ->middleware('throttle:30,1')
        ->name('gift-codes.result');
    Route::post('/gift-codes/{giftCode}/report', [GiftCodeController::class, 'report'])
        ->whereUlid('giftCode')
        ->middleware('throttle:20,1')
        ->name('gift-codes.report');
});
