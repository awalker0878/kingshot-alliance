<?php

declare(strict_types=1);

use App\Contexts\Communications\Delivery\Http\Controllers\NotificationCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');

    Route::put('/notifications/endpoints', [NotificationCenterController::class, 'saveEndpoint'])
        ->name('notifications.endpoints.store');
    Route::patch('/notifications/endpoints/{endpoint}', [NotificationCenterController::class, 'updateEndpoint'])
        ->whereUlid('endpoint')
        ->name('notifications.endpoints.update');
    Route::patch('/notifications/endpoints/{endpoint}/state', [NotificationCenterController::class, 'setEndpointState'])
        ->whereUlid('endpoint')
        ->name('notifications.endpoints.state');
    Route::post('/notifications/endpoints/{endpoint}/test', [NotificationCenterController::class, 'testEndpoint'])
        ->whereUlid('endpoint')
        ->name('notifications.endpoints.test');
    Route::post('/notifications/endpoints/{endpoint}/reverify', [NotificationCenterController::class, 'reverifyEndpoint'])
        ->whereUlid('endpoint')
        ->name('notifications.endpoints.reverify');
    Route::delete('/notifications/endpoints/{endpoint}', [NotificationCenterController::class, 'deleteEndpoint'])
        ->whereUlid('endpoint')
        ->name('notifications.endpoints.destroy');

    Route::put('/notifications/preferences', [NotificationCenterController::class, 'setPreference'])
        ->name('notifications.preferences.update');
    Route::delete('/notifications/preferences', [NotificationCenterController::class, 'resetPreference'])
        ->name('notifications.preferences.reset');

    Route::put('/notifications/routing-policy', [NotificationCenterController::class, 'setRoutingPolicy'])
        ->name('notifications.routing-policy.update');
    Route::delete('/notifications/routing-policy', [NotificationCenterController::class, 'resetRoutingPolicy'])
        ->name('notifications.routing-policy.reset');

    Route::post('/notifications/bulk/preview', [NotificationCenterController::class, 'previewBulkInboxUpdate'])
        ->name('notifications.bulk.preview');
    Route::put('/notifications/bulk', [NotificationCenterController::class, 'bulkInboxUpdate'])
        ->name('notifications.bulk.update');

    Route::put('/notifications/{message}/read', [NotificationCenterController::class, 'markRead'])
        ->whereUlid('message')
        ->name('notifications.read');
    Route::put('/notifications/{message}/unread', [NotificationCenterController::class, 'markUnread'])
        ->whereUlid('message')
        ->name('notifications.unread');
    Route::put('/notifications/{message}/archive', [NotificationCenterController::class, 'archive'])
        ->whereUlid('message')
        ->name('notifications.archive');
    Route::put('/notifications/{message}/restore', [NotificationCenterController::class, 'restore'])
        ->whereUlid('message')
        ->name('notifications.restore');
});
