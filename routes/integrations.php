<?php

declare(strict_types=1);

use App\Contexts\Platform\Integrations\Http\Controllers\IntegrationManagementController;
use App\Contexts\Platform\Integrations\Http\Controllers\ExternalActorConnectionController;
use App\ReadModels\ExternalActorConnections\Http\Controllers\ExternalActorConnectionsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'alliance.context'])->group(function (): void {
    Route::get('/profile/connections', ExternalActorConnectionsController::class)
        ->name('profile.connections.index');

    Route::get('/alliance/integrations', [IntegrationManagementController::class, 'index'])
        ->name('alliance.integrations.index');

    Route::middleware('password.confirm')->group(function (): void {
        Route::post('/profile/connections/pairing-codes', [ExternalActorConnectionController::class, 'issuePairingCode'])
            ->middleware('throttle:5,1')
            ->name('profile.connections.pairing-codes.store');
        Route::delete('/profile/connections/{link}', [ExternalActorConnectionController::class, 'revoke'])
            ->whereUlid('link')
            ->name('profile.connections.destroy');
        Route::post('/alliance/integrations/api-credentials', [IntegrationManagementController::class, 'createCredential'])
            ->middleware('throttle:10,1')
            ->name('alliance.integrations.api-credentials.store');
        Route::delete('/alliance/integrations/api-credentials/{credential}', [IntegrationManagementController::class, 'revokeCredential'])
            ->whereUlid('credential')
            ->name('alliance.integrations.api-credentials.destroy');
        Route::post('/alliance/integrations/webhooks', [IntegrationManagementController::class, 'createWebhook'])
            ->middleware('throttle:10,1')
            ->name('alliance.integrations.webhooks.store');
        Route::delete('/alliance/integrations/webhooks/{subscription}', [IntegrationManagementController::class, 'revokeWebhook'])
            ->whereUlid('subscription')
            ->name('alliance.integrations.webhooks.destroy');
        Route::post('/alliance/integrations/webhooks/{subscription}/test', [IntegrationManagementController::class, 'testWebhook'])
            ->whereUlid('subscription')
            ->middleware('throttle:10,1')
            ->name('alliance.integrations.webhooks.test');
        Route::post('/alliance/integrations/webhooks/{subscription}/rotate-secret', [IntegrationManagementController::class, 'rotateWebhookSecret'])
            ->whereUlid('subscription')
            ->middleware('throttle:5,1')
            ->name('alliance.integrations.webhooks.rotate-secret');
        Route::post('/alliance/integrations/webhook-deliveries/{delivery}/retry', [IntegrationManagementController::class, 'retryDelivery'])
            ->whereUlid('delivery')
            ->middleware('throttle:10,1')
            ->name('alliance.integrations.webhook-deliveries.retry');
    });
});
