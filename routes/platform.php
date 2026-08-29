<?php

declare(strict_types=1);

use App\Contexts\Platform\Administration\Http\Controllers\PlatformAdministrationController;
use App\ReadModels\EventTypeAdministration\Http\Controllers\EventTypeAdministrationReadController;
use App\ReadModels\PlatformAdministration\Http\Controllers\PlatformAdministrationReadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'platform.admin', 'password.confirm'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function (): void {
        Route::get('/', PlatformAdministrationReadController::class)
            ->name('administration.index');
        Route::get('/event-types', EventTypeAdministrationReadController::class)
            ->name('event-types.index');
        Route::post('/administrators', [PlatformAdministrationController::class, 'grantAdministrator'])
            ->name('administrators.store');
        Route::delete('/administrators/{administrator}', [PlatformAdministrationController::class, 'revokeAdministrator'])
            ->whereUlid('administrator')
            ->name('administrators.destroy');
        Route::post('/alliances/{alliance}/lifecycle/{operation}', [PlatformAdministrationController::class, 'lifecycle'])
            ->whereUlid('alliance')
            ->whereIn('operation', ['suspend', 'close', 'delete', 'restore'])
            ->name('alliances.lifecycle');
        Route::put('/alliances/{alliance}/plan', [PlatformAdministrationController::class, 'assignPlan'])
            ->whereUlid('alliance')
            ->name('alliances.plan');
        Route::put('/alliances/{alliance}/settings', [PlatformAdministrationController::class, 'updateSettings'])
            ->whereUlid('alliance')
            ->name('alliances.settings');
        Route::put('/alliances/{alliance}/features', [PlatformAdministrationController::class, 'setFeature'])
            ->whereUlid('alliance')
            ->name('alliances.features');
        Route::post('/alliances/{alliance}/usage', [PlatformAdministrationController::class, 'captureUsage'])
            ->whereUlid('alliance')
            ->name('alliances.usage');
        Route::get('/alliances/{alliance}/export.json', [PlatformAdministrationController::class, 'export'])
            ->whereUlid('alliance')
            ->middleware('throttle:5,1')
            ->name('alliances.export');
        Route::post('/legal-holds', [PlatformAdministrationController::class, 'placeLegalHold'])
            ->name('legal-holds.store');
        Route::delete('/legal-holds/{hold}', [PlatformAdministrationController::class, 'releaseLegalHold'])
            ->whereUlid('hold')
            ->name('legal-holds.destroy');
        Route::post('/operations/outbox/{message}/retry', [PlatformAdministrationController::class, 'retryOutbox'])
            ->whereUlid('message')
            ->middleware('throttle:10,1')
            ->name('operations.outbox.retry');
    });
