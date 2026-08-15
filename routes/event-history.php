<?php

declare(strict_types=1);

use App\Domain\Events\Http\Controllers\EventHistoryController;
use App\Domain\Events\Http\Controllers\EventResultController;
use App\Domain\Events\Http\Controllers\EventRosterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/alliances/{alliance}/events/history', [EventHistoryController::class, 'alliance'])
        ->whereUlid('alliance')
        ->name('alliances.events.history');
    Route::get('/kingdoms/{kingdom}/events/history', [EventHistoryController::class, 'kingdom'])
        ->whereUlid('kingdom')
        ->name('kingdoms.events.history');
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
