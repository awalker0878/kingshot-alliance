<?php

declare(strict_types=1);

use App\Contexts\Communications\Delivery\Http\Controllers\NotificationCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');

    Route::put('/notifications/endpoints', [NotificationCenterController::class, 'saveEndpoint'])
        ->name('notifications.endpoints.store');
    Route::delete('/notifications/endpoints/{endpoint}', [NotificationCenterController::class, 'deleteEndpoint'])
        ->name('notifications.endpoints.destroy');

    Route::put('/notifications/preferences', [NotificationCenterController::class, 'setPreference'])
        ->name('notifications.preferences.update');
    Route::delete('/notifications/preferences', [NotificationCenterController::class, 'resetPreference'])
        ->name('notifications.preferences.reset');

    Route::post('/notifications/bulk/preview', [NotificationCenterController::class, 'previewBulkInboxUpdate'])
        ->name('notifications.bulk.preview');
    Route::put('/notifications/bulk', [NotificationCenterController::class, 'bulkInboxUpdate'])
        ->name('notifications.bulk.update');

    Route::put('/notifications/{message}/read', [NotificationCenterController::class, 'markRead'])
        ->name('notifications.read');
    Route::put('/notifications/{message}/unread', [NotificationCenterController::class, 'markUnread'])
        ->name('notifications.unread');
    Route::put('/notifications/{message}/archive', [NotificationCenterController::class, 'archive'])
        ->name('notifications.archive');
    Route::put('/notifications/{message}/restore', [NotificationCenterController::class, 'restore'])
        ->name('notifications.restore');
});
