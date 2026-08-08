<?php

declare(strict_types=1);

use App\Domain\Integrations\Http\Controllers\AllianceApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::get('/alliance', [AllianceApiController::class, 'show'])
        ->middleware('api.credential:alliance:read')
        ->name('api.v1.alliance.show');
    Route::get('/events', [AllianceApiController::class, 'events'])
        ->middleware('api.credential:events:read')
        ->name('api.v1.events.index');
    Route::get('/contributions', [AllianceApiController::class, 'contributions'])
        ->middleware('api.credential:contributions:read')
        ->name('api.v1.contributions.index');
});
