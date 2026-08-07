<?php

declare(strict_types=1);

use App\Http\Controllers\Alliance\ActivateAllianceController;
use App\Http\Controllers\Alliance\AllianceOverviewController;
use App\Http\Controllers\Alliance\CreateAllianceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', static fn () => Inertia::render('Home', [
    'application' => [
        'name' => config('app.name'),
    ],
]))->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])
        ->middleware('throttle:registration')
        ->name('register.store');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/alliances', CreateAllianceController::class)->name('alliances.store');
    Route::put('/alliances/{alliance}/active', ActivateAllianceController::class)
        ->whereUlid('alliance')
        ->name('alliances.activate');
    Route::delete('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('alliance.context')->group(function (): void {
        Route::get('/alliance', AllianceOverviewController::class)->name('alliance.overview');
    });
});
