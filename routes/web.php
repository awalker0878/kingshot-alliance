<?php

declare(strict_types=1);

use App\Http\Controllers\Alliance\ActivateAllianceController;
use App\Http\Controllers\Alliance\AllianceOverviewController;
use App\Http\Controllers\Alliance\ContentManagementController;
use App\Http\Controllers\Alliance\CreateAllianceController;
use App\Http\Controllers\Alliance\EventCalendarController;
use App\Http\Controllers\Alliance\EventManagementController;
use App\Http\Controllers\Alliance\InvitationController;
use App\Http\Controllers\Alliance\MemberContentController;
use App\Http\Controllers\Alliance\MembershipController;
use App\Http\Controllers\Alliance\RecruitmentCandidateController;
use App\Http\Controllers\Alliance\RecruitmentManagementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmPasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAllianceController;
use App\Http\Controllers\PublicBrandingMediaController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\PublicRecruitmentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', static fn () => Inertia::render('Home', [
    'application' => [
        'name' => config('app.name'),
    ],
]))->name('home');

Route::get('/alliances/{slug}', PublicAllianceController::class)
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('public.alliances.show');
Route::get('/alliances/{slug}/content/{contentSlug}', PublicContentController::class)
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->where('contentSlug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('public.alliances.content.show');
Route::get('/alliances/{slug}/branding/{slot}', PublicBrandingMediaController::class)
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->whereIn('slot', ['logo', 'banner'])
    ->name('public.alliances.branding');
Route::get('/alliances/{slug}/apply', [PublicRecruitmentController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('public.alliances.recruitment.show');
Route::post('/alliances/{slug}/apply', [PublicRecruitmentController::class, 'store'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware('throttle:recruitment-application')
    ->name('public.alliances.recruitment.store');

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

    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.login');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:two-factor-challenge')
        ->name('two-factor.login.store');

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
        Route::middleware('password.confirm')->group(function (): void {
            Route::post('/profile/two-factor', [TwoFactorController::class, 'begin'])
                ->name('two-factor.enable');
            Route::post('/profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])
                ->name('two-factor.confirm');
            Route::post('/profile/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])
                ->name('two-factor.recovery-codes');
            Route::delete('/profile/two-factor', [TwoFactorController::class, 'destroy'])
                ->name('two-factor.disable');
        });

        Route::post('/alliances', CreateAllianceController::class)->name('alliances.store');
        Route::put('/alliances/{alliance}/active', ActivateAllianceController::class)
            ->whereUlid('alliance')
            ->name('alliances.activate');
        Route::post('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'accept'])
            ->where('token', '[A-Fa-f0-9]{64}')
            ->name('invitations.accept');

        Route::middleware('alliance.context')->group(function (): void {
            Route::get('/alliance', AllianceOverviewController::class)->name('alliance.overview');

            Route::get('/alliance/recruitment', [RecruitmentManagementController::class, 'index'])
                ->name('alliance.recruitment.index');
            Route::get('/alliance/recruitment/{candidate}', [RecruitmentCandidateController::class, 'show'])
                ->whereUlid('candidate')
                ->name('alliance.recruitment.candidates.show');

            Route::get('/alliance/content', [MemberContentController::class, 'index'])
                ->name('alliance.content.index');
            Route::get('/alliance/content/manage', [ContentManagementController::class, 'index'])
                ->name('alliance.content.manage');
            Route::get('/alliance/content/{contentSlug}', [MemberContentController::class, 'show'])
                ->where('contentSlug', '[a-z0-9]+(?:-[a-z0-9]+)*')
                ->name('alliance.content.show');

            Route::get('/alliance/events', [EventCalendarController::class, 'index'])
                ->name('alliance.events.index');
            Route::get('/alliance/events/manage', [EventManagementController::class, 'index'])
                ->name('alliance.events.manage');
            Route::get('/alliance/events/export.csv', [EventCalendarController::class, 'export'])
                ->name('alliance.events.export');
            Route::get('/alliance/events/feed.ics', [EventCalendarController::class, 'ical'])
                ->name('alliance.events.ical');
            Route::post('/alliance/formations', [EventCalendarController::class, 'saveFormation'])
                ->name('alliance.formations.store');
            Route::post('/alliance/events/{occurrence}/registration', [EventCalendarController::class, 'register'])
                ->whereUlid('occurrence')
                ->name('alliance.events.registration.store');
            Route::delete('/alliance/events/{occurrence}/registration', [EventCalendarController::class, 'cancel'])
                ->whereUlid('occurrence')
                ->name('alliance.events.registration.destroy');
            Route::get('/alliance/events/{occurrence}', [EventCalendarController::class, 'show'])
                ->whereUlid('occurrence')
                ->name('alliance.events.show');

            Route::middleware('password.confirm')->group(function (): void {
                Route::patch('/alliance/recruitment/settings', [RecruitmentManagementController::class, 'updateSettings'])
                    ->name('alliance.recruitment.settings.update');
                Route::post('/alliance/recruitment/questions', [RecruitmentManagementController::class, 'storeQuestion'])
                    ->name('alliance.recruitment.questions.store');
                Route::post('/alliance/recruitment/application-invites', [RecruitmentManagementController::class, 'issueApplicationInvite'])
                    ->name('alliance.recruitment.application-invites.store');
                Route::post('/alliance/recruitment/decision-templates', [RecruitmentManagementController::class, 'storeDecisionTemplate'])
                    ->name('alliance.recruitment.decision-templates.store');
                Route::post('/alliance/recruitment/onboarding-items', [RecruitmentManagementController::class, 'storeOnboardingItem'])
                    ->name('alliance.recruitment.onboarding-items.store');
                Route::patch('/alliance/recruitment/{candidate}/stage', [RecruitmentCandidateController::class, 'updateStage'])
                    ->whereUlid('candidate')
                    ->name('alliance.recruitment.candidates.stage.update');
                Route::put('/alliance/recruitment/{candidate}/reviewers/{membership}', [RecruitmentCandidateController::class, 'assignReviewer'])
                    ->whereUlid('candidate')
                    ->whereUlid('membership')
                    ->name('alliance.recruitment.candidates.reviewers.store');
                Route::post('/alliance/recruitment/{candidate}/notes', [RecruitmentCandidateController::class, 'addNote'])
                    ->whereUlid('candidate')
                    ->name('alliance.recruitment.candidates.notes.store');
                Route::put('/alliance/recruitment/{candidate}/tags', [RecruitmentCandidateController::class, 'tag'])
                    ->whereUlid('candidate')
                    ->name('alliance.recruitment.candidates.tags.store');
                Route::post('/alliance/recruitment/{candidate}/merge/{target}', [RecruitmentCandidateController::class, 'merge'])
                    ->whereUlid('candidate')
                    ->whereUlid('target')
                    ->name('alliance.recruitment.candidates.merge');
                Route::post('/alliance/recruitment/{candidate}/communications/{template}', [RecruitmentCandidateController::class, 'prepareCommunication'])
                    ->whereUlid('candidate')
                    ->whereUlid('template')
                    ->name('alliance.recruitment.candidates.communications.store');
                Route::patch('/alliance/recruitment/communications/{communication}/sent', [RecruitmentCandidateController::class, 'markCommunicationSent'])
                    ->whereUlid('communication')
                    ->name('alliance.recruitment.communications.sent');
                Route::post('/alliance/recruitment/{candidate}/convert', [RecruitmentCandidateController::class, 'convert'])
                    ->whereUlid('candidate')
                    ->name('alliance.recruitment.candidates.convert');
                Route::patch('/alliance/recruitment/onboarding/{onboarding}', [RecruitmentCandidateController::class, 'updateOnboarding'])
                    ->whereUlid('onboarding')
                    ->name('alliance.recruitment.onboarding.update');

                Route::post('/alliance/events', [EventManagementController::class, 'storeEvent'])
                    ->name('alliance.events.store');
                Route::post('/alliance/event-templates', [EventManagementController::class, 'storeTemplate'])
                    ->name('alliance.event-templates.store');
                Route::post('/alliance/event-templates/events', [EventManagementController::class, 'storeTemplateEvent'])
                    ->name('alliance.event-templates.events.store');
                Route::post('/alliance/events/{event}/reminders', [EventManagementController::class, 'storeReminder'])
                    ->whereUlid('event')
                    ->name('alliance.events.reminders.store');
                Route::post('/alliance/rally-guidance', [EventManagementController::class, 'storeGuidance'])
                    ->name('alliance.rally-guidance.store');
                Route::post('/alliance/events/{occurrence}/formations', [EventManagementController::class, 'storeRecommendedFormation'])
                    ->whereUlid('occurrence')
                    ->name('alliance.events.formations.store');
                Route::post('/alliance/events/{occurrence}/rally-groups', [EventManagementController::class, 'storeRallyGroup'])
                    ->whereUlid('occurrence')
                    ->name('alliance.events.rally-groups.store');
                Route::put('/alliance/rally-groups/{group}/assignments', [EventManagementController::class, 'assignMember'])
                    ->whereUlid('group')
                    ->name('alliance.rally-groups.assignments.store');
                Route::patch('/alliance/events/{occurrence}/registrations/{registration}/attendance', [EventManagementController::class, 'recordAttendance'])
                    ->whereUlid('occurrence')
                    ->whereUlid('registration')
                    ->name('alliance.events.attendance.update');
                Route::patch('/alliance/rally-assignments/{assignment}/participation', [EventManagementController::class, 'recordParticipation'])
                    ->whereUlid('assignment')
                    ->name('alliance.rally-assignments.participation.update');

                Route::patch('/alliance/public-profile', [ContentManagementController::class, 'updateProfile'])
                    ->name('alliance.public-profile.update');

                Route::post('/alliance/content/categories', [ContentManagementController::class, 'storeCategory'])
                    ->name('alliance.content.categories.store');
                Route::patch('/alliance/content/categories/{category}', [ContentManagementController::class, 'updateCategory'])
                    ->whereUlid('category')
                    ->name('alliance.content.categories.update');
                Route::delete('/alliance/content/categories/{category}', [ContentManagementController::class, 'destroyCategory'])
                    ->whereUlid('category')
                    ->name('alliance.content.categories.destroy');

                Route::post('/alliance/content', [ContentManagementController::class, 'storeContent'])
                    ->name('alliance.content.store');
                Route::patch('/alliance/content/{content}', [ContentManagementController::class, 'updateContent'])
                    ->whereUlid('content')
                    ->name('alliance.content.update');
                Route::post('/alliance/content/{content}/publish', [ContentManagementController::class, 'publishContent'])
                    ->whereUlid('content')
                    ->name('alliance.content.publish');
                Route::delete('/alliance/content/{content}', [ContentManagementController::class, 'archiveContent'])
                    ->whereUlid('content')
                    ->name('alliance.content.archive');
                Route::post('/alliance/content/{content}/revisions/{revision}/restore', [ContentManagementController::class, 'restoreRevision'])
                    ->whereUlid('content')
                    ->whereUlid('revision')
                    ->name('alliance.content.revisions.restore');

                Route::post('/alliance/media', [ContentManagementController::class, 'storeMedia'])
                    ->name('alliance.media.store');
                Route::delete('/alliance/media/{media}', [ContentManagementController::class, 'archiveMedia'])
                    ->whereUlid('media')
                    ->name('alliance.media.archive');

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