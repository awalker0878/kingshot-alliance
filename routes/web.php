<?php

declare(strict_types=1);

use App\Http\Controllers\Alliance\ActivateAllianceController;
use App\Http\Controllers\Alliance\AllianceOverviewController;
use App\Http\Controllers\Alliance\CreateAllianceController;
use App\Http\Controllers\Alliance\InvitationController;
use App\Http\Controllers\Alliance\MembershipController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', static fn () => Inertia::render('Home', [
    'application' => [
        'name' => config('app.name'),
    ],
]))->name('home');

Route::get('/invitations/{token}', [InvitationAcceptanceController::class, 'show'])
    ->where('token', '[A-Fa-f0-9]{64}')
    ->name('invitations.show');

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
    Route::post('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'accept'])
        ->where('token', '[A-Fa-f0-9]{64}')
        ->name('invitations.accept');
    Route::delete('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('alliance.context')->group(function (): void {
        Route::get('/alliance', AllianceOverviewController::class)->name('alliance.overview');
        Route::post('/alliance/invitations', [InvitationController::class, 'store'])
            ->name('alliance.invitations.store');
        Route::post('/alliance/invitations/{invitation}/resend', [InvitationController::class, 'resend'])
            ->whereUlid('invitation')
            ->name('alliance.invitations.resend');
        Route::delete('/alliance/invitations/{invitation}', [InvitationController::class, 'destroy'])
            ->whereUlid('invitation')
            ->name('alliance.invitations.destroy');

        Route::patch('/alliance/memberships/{membership}/status', [MembershipController::class, 'updateStatus'])
            ->whereUlid('membership')
            ->name('alliance.memberships.status');
        Route::put('/alliance/memberships/{membership}/roles/{role}', [MembershipController::class, 'assignRole'])
            ->whereUlid('membership')
            ->whereUlid('role')
            ->name('alliance.memberships.roles.assign');
        Route::delete('/alliance/memberships/{membership}/roles/{role}', [MembershipController::class, 'removeRole'])
            ->whereUlid('membership')
            ->whereUlid('role')
            ->name('alliance.memberships.roles.remove');
        Route::delete('/alliance/membership', [MembershipController::class, 'leave'])
            ->name('alliance.membership.leave');
    });
});
