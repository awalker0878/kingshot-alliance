<?php

declare(strict_types=1);

use App\Contexts\Communications\Delivery\Http\Controllers\NotificationCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/notifications', [NotificationCenterController::class, 'index'])
        ->name('notifications.index');
    Route::put('/notifications/endpoints', [NotificationCenterController::class, 'saveEndpoint'])
        ->middleware('throttle:10,1')
        ->name('notifications.endpoints.save');
    Route::delete('/notifications/endpoints/{endpoint}', [NotificationCenterController::class, 'deleteEndpoint'])
        ->whereUlid('endpoint')
        ->name('notifications.endpoints.delete');
    Route::put('/notifications/preferences', [NotificationCenterController::class, 'setPreference'])
        ->name('notifications.preferences.update');
    Route::post('/notifications/bulk-inbox/preview', [NotificationCenterController::class, 'previewBulkInboxUpdate'])
        ->name('notifications.bulk-inbox.preview');
    Route::post('/notifications/bulk-inbox', [NotificationCenterController::class, 'bulkInboxUpdate'])
        ->name('notifications.bulk-inbox.update');
    Route::put('/notifications/{delivery}/read', [NotificationCenterController::class, 'markRead'])
        ->whereUlid('delivery')
        ->name('notifications.read');
    Route::delete('/notifications/{delivery}', [NotificationCenterController::class, 'dismiss'])
        ->whereUlid('delivery')
        ->name('notifications.dismiss');
});
