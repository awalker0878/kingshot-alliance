<?php

declare(strict_types=1);

use App\Domain\Events\Http\Controllers\EventResultController;
use App\Domain\Events\Http\Controllers\EventRosterController;
use Illuminate\Support\Facades\Route;

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
