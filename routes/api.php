<?php

declare(strict_types=1);

use App\Contexts\Platform\Integrations\Http\Controllers\AllianceApiController;
use App\ReadModels\BotCommands\Http\Controllers\BotCommandApiController;
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

    Route::get('/commands/overview', [BotCommandApiController::class, 'overview'])
        ->middleware('api.credential:commands:read')
        ->name('api.v1.commands.overview');
    Route::get('/commands/gift-codes', [BotCommandApiController::class, 'giftCodes'])
        ->middleware('api.credential:gift-codes:read')
        ->name('api.v1.commands.gift-codes');
    Route::get('/commands/knowledge', [BotCommandApiController::class, 'knowledge'])
        ->middleware('api.credential:content:read')
        ->name('api.v1.commands.knowledge');
});
