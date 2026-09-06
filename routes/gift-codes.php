<?php

declare(strict_types=1);

use App\Contexts\GameWorld\GiftCodes\Http\Controllers\GiftCodeController;
use App\Contexts\GameWorld\GiftCodes\Http\Controllers\GiftCodeModerationController;
use App\Contexts\GameWorld\GiftCodes\Http\Controllers\GiftCodeSourceManagementController;
use App\Contexts\GameWorld\GiftCodes\Http\Controllers\GiftCodeWorkspaceController;
use App\ReadModels\GiftCodes\Http\Controllers\GiftCodeAllianceCoverageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/gift-codes', [GiftCodeController::class, 'index'])
        ->name('gift-codes.index');
    Route::get('/gift-codes/workspace', [GiftCodeWorkspaceController::class, 'index'])
        ->name('gift-codes.workspace');
    Route::get('/gift-codes/workspace/alliance/{alliance}/coverage', GiftCodeAllianceCoverageController::class)
        ->whereUlid('alliance')
        ->name('gift-codes.workspace.alliance-coverage');
    Route::post('/gift-codes/workspace/sessions', [GiftCodeWorkspaceController::class, 'createSession'])
        ->middleware('throttle:20,1')
        ->name('gift-codes.workspace.sessions.store');
    Route::post('/gift-codes/workspace/state/{giftCode}', [GiftCodeWorkspaceController::class, 'updateState'])
        ->whereUlid('giftCode')
        ->middleware('throttle:30,1')
        ->name('gift-codes.workspace.state');
    Route::post('/gift-codes/workspace/sessions/{session}/items/{item}/prepare', [GiftCodeWorkspaceController::class, 'prepareItem'])
        ->whereUlid('session')
        ->whereUlid('item')
        ->middleware('throttle:30,1')
        ->name('gift-codes.workspace.sessions.items.prepare');
    Route::post('/gift-codes/workspace/sessions/{session}/items/{item}/result', [GiftCodeWorkspaceController::class, 'resultItem'])
        ->whereUlid('session')
        ->whereUlid('item')
        ->middleware('throttle:30,1')
        ->name('gift-codes.workspace.sessions.items.result');
    Route::post('/gift-codes/workspace/sessions/{session}/items/{item}/skip', [GiftCodeWorkspaceController::class, 'skipItem'])
        ->whereUlid('session')
        ->whereUlid('item')
        ->middleware('throttle:30,1')
        ->name('gift-codes.workspace.sessions.items.skip');
    Route::post('/gift-codes/workspace/sessions/{session}/abandon', [GiftCodeWorkspaceController::class, 'abandon'])
        ->whereUlid('session')
        ->middleware('throttle:10,1')
        ->name('gift-codes.workspace.sessions.abandon');

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
        Route::get('/sources', [GiftCodeSourceManagementController::class, 'index'])
            ->name('sources.index');
        Route::post('/sources', [GiftCodeModerationController::class, 'storeSource'])
            ->middleware('throttle:10,1')
            ->name('sources.store');
        Route::post('/sources/policy', [GiftCodeSourceManagementController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('sources.policy');
        Route::post('/sources/evidence', [GiftCodeSourceManagementController::class, 'evidence'])
            ->middleware('throttle:20,1')
            ->name('sources.evidence');
        Route::post('/sources/{source}/push/subscribe', [GiftCodeSourceManagementController::class, 'subscribePush'])
            ->whereUlid('source')
            ->middleware('throttle:10,1')
            ->name('sources.push.subscribe');
        Route::post('/sources/{source}/push/unsubscribe', [GiftCodeSourceManagementController::class, 'unsubscribePush'])
            ->whereUlid('source')
            ->middleware('throttle:10,1')
            ->name('sources.push.unsubscribe');
        Route::post('/sources/{source}/reconcile', [GiftCodeSourceManagementController::class, 'reconcile'])
            ->whereUlid('source')
            ->middleware('throttle:10,1')
            ->name('sources.reconcile');
        Route::post('/sources/{source}/backfill', [GiftCodeSourceManagementController::class, 'backfill'])
            ->whereUlid('source')
            ->middleware('throttle:10,1')
            ->name('sources.backfill');
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