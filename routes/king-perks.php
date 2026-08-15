<?php

declare(strict_types=1);

use App\Domain\KingPerks\Http\Controllers\KingPerkController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/events/{event}/king-perks', [KingPerkController::class, 'index'])
        ->whereUlid('event')
        ->name('events.king-perks.index');

    Route::post('/king-perk-appointments/{appointment}/confirm', [KingPerkController::class, 'confirm'])
        ->whereUlid('appointment')
        ->name('king-perks.appointments.confirm');

    Route::middleware('password.confirm')->group(function (): void {
        Route::post('/events/{event}/occurrences/{occurrence}/king-perks', [KingPerkController::class, 'createPlan'])
            ->whereUlid('event')
            ->whereUlid('occurrence')
            ->name('events.king-perks.plans.store');
        Route::post('/king-perk-plans/{plan}/publish', [KingPerkController::class, 'publish'])
            ->whereUlid('plan')
            ->name('king-perks.plans.publish');
        Route::post('/king-perk-plans/{plan}/appointments', [KingPerkController::class, 'assign'])
            ->whereUlid('plan')
            ->name('king-perks.appointments.store');
        Route::patch('/king-perk-appointments/{appointment}/outcome', [KingPerkController::class, 'outcome'])
            ->whereUlid('appointment')
            ->name('king-perks.appointments.outcome');
        Route::post('/king-perk-appointments/{appointment}/cancelled-cooldown', [KingPerkController::class, 'cancelledCooldown'])
            ->whereUlid('appointment')
            ->name('king-perks.appointments.cancelled-cooldown');
        Route::post('/king-perk-plans/{plan}/skills', [KingPerkController::class, 'planSkill'])
            ->whereUlid('plan')
            ->name('king-perks.skills.store');
        Route::post('/king-skill-plans/{skill}/scheduled', [KingPerkController::class, 'skillScheduled'])
            ->whereUlid('skill')
            ->name('king-perks.skills.scheduled');
        Route::post('/king-skill-plans/{skill}/activated', [KingPerkController::class, 'skillActivated'])
            ->whereUlid('skill')
            ->name('king-perks.skills.activated');
    });
});
