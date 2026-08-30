<?php

declare(strict_types=1);

use App\Contexts\GameWorld\GiftCodes\Http\Controllers\GiftCodeController;
use App\Contexts\GameWorld\GiftCodes\Http\Controllers\GiftCodeModerationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/gift-codes', [GiftCodeController::class, 'index'])
        ->name('gift-codes.index');
    Route::get('/gift-codes/{giftCode}', [GiftCodeController::class, 'show'])
        ->whereUlid('giftCode')
        ->name('gift-codes.show');
    Route::post('/gift-codes', [GiftCodeController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('gift-codes.store');
    Route::post('/gift-codes/{giftCode}/redeem', [GiftCodeController::class, 'redeem'])
        ->whereUlid('giftCode')
        ->middleware('throttle:30,1')
        ->name('gift-codes.redeem');
    Route::post('/gift-codes/{giftCode}/result', [GiftCodeController::class, 'result'])
        ->whereUlid('giftCode')
        ->middleware('throttle:30,1')
        ->name('gift-codes.result');
});

Route::middleware(['auth', 'auth.session', 'verified', 'gift-code.curator', 'password.confirm'])
    ->prefix('platform/gift-codes')
    ->name('platform.gift-codes.')
    ->group(function (): void {
        Route::get('/', [GiftCodeModerationController::class, 'index'])->name('index');
        Route::post('/bulk', [GiftCodeModerationController::class, 'bulk'])
            ->middleware('throttle:10,1')
            ->name('bulk');
        Route::post('/sources', [GiftCodeModerationController::class, 'storeSource'])
            ->middleware('throttle:10,1')
            ->name('sources.store');
        Route::post('/sources/{source}/revoke', [GiftCodeModerationController::class, 'revokeSource'])
            ->whereUlid('source')
            ->middleware('throttle:10,1')
            ->name('sources.revoke');
        Route::post('/curators', [GiftCodeModerationController::class, 'grantCurator'])
            ->middleware('throttle:10,1')
            ->name('curators.store');
        Route::post('/curators/{grant}/revoke', [GiftCodeModerationController::class, 'revokeCurator'])
            ->whereUlid('grant')
            ->middleware('throttle:10,1')
            ->name('curators.revoke');
        Route::post('/{giftCode}', [GiftCodeModerationController::class, 'moderate'])
            ->whereUlid('giftCode')
            ->middleware('throttle:20,1')
            ->name('moderate');
    });
