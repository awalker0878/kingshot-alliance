<?php

declare(strict_types=1);

use App\Contexts\GameWorld\GiftCodes\Http\Controllers\GiftCodeSourceWebhookController;
use App\Contexts\Platform\Integrations\Http\Controllers\AllianceApiController;
use App\ReadModels\BotCommands\Http\Controllers\BotCommandApiController;
use App\ReadModels\BotCommands\Http\Controllers\GiftCodeApiController;
use App\Workflows\ExternalEventParticipation\Http\Controllers\ExternalActorApiController;
use Illuminate\Support\Facades\Route;

Route::post('/internal/gift-code-sources/{source}/observations', GiftCodeSourceWebhookController::class)
    ->whereUlid('source')
    ->middleware('throttle:60,1')
    ->name('api.internal.gift-code-sources.observations');

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
    Route::get('/gift-codes', GiftCodeApiController::class)
        ->middleware('api.credential:gift-codes:read')
        ->name('api.v1.gift-codes.index');
    Route::get('/commands/knowledge', [BotCommandApiController::class, 'knowledge'])
        ->middleware('api.credential:content:read')
        ->name('api.v1.commands.knowledge');

    Route::post('/actor-links/claims', [ExternalActorApiController::class, 'claim'])
        ->middleware(['api.credential:actor-links:write', 'throttle:10,1'])
        ->name('api.v1.actor-links.claim');
    Route::put('/me/events/{occurrence}/response', [ExternalActorApiController::class, 'respond'])
        ->whereUlid('occurrence')
        ->middleware('api.credential:event-participation:write')
        ->name('api.v1.me.events.response');
    Route::put('/me/events/{occurrence}/registration', [ExternalActorApiController::class, 'registration'])
        ->whereUlid('occurrence')
        ->middleware('api.credential:event-participation:write')
        ->name('api.v1.me.events.registration');
});
