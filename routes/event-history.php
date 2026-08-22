<?php

declare(strict_types=1);

use App\Contexts\Intelligence\Evidence\Http\Controllers\EvidenceController;
use App\Contexts\Operations\Results\Http\Controllers\EventResultController;
use App\Contexts\Operations\Rosters\Http\Controllers\EventRosterController;
use App\ReadModels\EventHistory\Http\Controllers\EventHistoryController;
use App\ReadModels\ScreenshotIntake\Http\Controllers\ScreenshotIntakeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/alliances/{alliance}/events/history', [EventHistoryController::class, 'alliance'])
        ->whereUlid('alliance')
        ->name('alliances.events.history');
    Route::get('/kingdoms/{kingdom}/events/history', [EventHistoryController::class, 'kingdom'])
        ->whereUlid('kingdom')
        ->name('kingdoms.events.history');

    Route::get('/events/{occurrence}/screenshot-intake', ScreenshotIntakeController::class)
        ->whereUlid('occurrence')
        ->name('events.screenshot-intake');
    Route::post('/events/{occurrence}/screenshot-intake', [EvidenceController::class, 'store'])
        ->whereUlid('occurrence')
        ->name('events.screenshot-intake.store');
});

Route::middleware(['auth', 'auth.session', 'verified', 'password.confirm'])->group(function (): void {
    Route::put('/events/{occurrence}/results/alliances/{alliance}', [EventResultController::class, 'saveAlliance'])
        ->whereUlid('occurrence')
        ->whereUlid('alliance')
        ->name('events.alliance-results.update');

    Route::patch('/events/{occurrence}/roster-members/{member}/participation', [EventRosterController::class, 'participation'])
        ->whereUlid('occurrence')
        ->whereUlid('member')
        ->name('events.roster-members.participation');
});
