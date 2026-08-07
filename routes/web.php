<?php

declare(strict_types=1);

use App\Http\Controllers\Alliance\ActivateAllianceController;
use App\Http\Controllers\Alliance\AllianceOverviewController;
use App\Http\Controllers\Alliance\CreateAllianceController;
use App\Http\Controllers\Alliance\InvitationController;
use App\Http\Controllers\Alliance\MembershipController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmPasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
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

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::delete('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');
    Route::delete('/profile/sessions/other', [ProfileController::class, 'destroyOtherSessions'])
        ->name('profile.sessions.destroy-other');

    Route::get('/verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/confirm-password', [ConfirmPasswordController::class, 'create'])
        ->name('password.confirm');
    Route::post('/confirm-password', [ConfirmPasswordController::class, 'store'])
        ->name('password.confirm.store');

    Route::middleware('verified')->group(function (): void {
        Route::post('/alliances', CreateAllianceController::class)->name('alliances.store');
        Route::put('/alliances/{alliance}/active', ActivateAllianceController::class)
            ->whereUlid('alliance')
            ->name('alliances.activate');
        Route::post('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'accept'])
            ->where('token', '[A-Fa-f0-9]{64}')
            ->name('invitations.accept');

        Route::middleware('alliance.context')->group(function (): void {
            Route::get('/alliance', AllianceOverviewController::class)->name('alliance.overview');

            Route::middleware('password.confirm')->group(function (): void {
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
    });
});
