<?php

declare(strict_types=1);

use App\Domain\Integrations\Http\Controllers\IntegrationManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'alliance.context'])->group(function (): void {
    Route::get('/alliance/integrations', [IntegrationManagementController::class, 'index'])
        ->name('alliance.integrations.index');

    Route::middleware('password.confirm')->group(function (): void {
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
    });
});
