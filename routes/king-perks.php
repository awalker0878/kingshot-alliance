<?php

declare(strict_types=1);

use App\Domain\KingPerks\Http\Controllers\KingPerkController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/events/{event}/king-perks', [KingPerkController::class, 'index'])
        ->whereUlid('event')
        ->name('events.king-perks.index');
    Route::get('/events/{event}/king-perks/my', [KingPerkController::class, 'my'])
        ->whereUlid('event')
        ->name('events.king-perks.my');

    Route::post('/king-perk-appointments/{appointment}/confirm', [KingPerkController::class, 'confirm'])
        ->whereUlid('appointment')
        ->name('king-perks.appointments.confirm');
    Route::post('/king-perk-appointments/{appointment}/decline', [KingPerkController::class, 'declineAppointment'])
        ->whereUlid('appointment')
        ->name('king-perks.appointments.decline');
    Route::post('/king-perk-plans/{plan}/requests', [KingPerkController::class, 'submitRequest'])
        ->whereUlid('plan')
        ->name('king-perks.requests.store');
    Route::delete('/king-perk-requests/{perkRequest}', [KingPerkController::class, 'withdrawRequest'])
        ->whereUlid('perkRequest')
        ->name('king-perks.requests.withdraw');

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
        Route::post('/king-perk-appointments/{appointment}/active', [KingPerkController::class, 'activateAppointment'])
            ->whereUlid('appointment')
            ->name('king-perks.appointments.active');
        Route::patch('/king-perk-appointments/{appointment}/outcome', [KingPerkController::class, 'outcome'])
            ->whereUlid('appointment')
            ->name('king-perks.appointments.outcome');
        Route::post('/king-perk-appointments/{appointment}/replace', [KingPerkController::class, 'replace'])
            ->whereUlid('appointment')
            ->name('king-perks.appointments.replace');
        Route::post('/king-perk-appointments/{appointment}/cancelled-cooldown', [KingPerkController::class, 'cancelledCooldown'])
            ->whereUlid('appointment')
            ->name('king-perks.appointments.cancelled-cooldown');
        Route::post('/king-perk-requests/{perkRequest}/decline', [KingPerkController::class, 'declineRequest'])
            ->whereUlid('perkRequest')
            ->name('king-perks.requests.decline');
        Route::post('/king-perk-plans/{plan}/auto-schedule', [KingPerkController::class, 'autoSchedule'])
            ->whereUlid('plan')
            ->name('king-perks.auto-schedule');
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
