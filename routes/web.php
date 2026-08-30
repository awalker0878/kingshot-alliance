<?php

declare(strict_types=1);

use App\Contexts\Accounts\Authentication\Http\Controllers\AuthenticatedSessionController;
use App\Contexts\Accounts\Authentication\Http\Controllers\ConfirmPasswordController;
use App\Contexts\Accounts\Credentials\Http\Controllers\ForgotPasswordController;
use App\Contexts\Accounts\Credentials\Http\Controllers\ResetPasswordController;
use App\Contexts\Accounts\EmailVerification\Http\Controllers\EmailVerificationNotificationController;
use App\Contexts\Accounts\EmailVerification\Http\Controllers\EmailVerificationPromptController;
use App\Contexts\Accounts\EmailVerification\Http\Controllers\VerifyEmailController;
use App\Contexts\Accounts\MultiFactorAuthentication\Http\Controllers\TwoFactorChallengeController;
use App\Contexts\Accounts\MultiFactorAuthentication\Http\Controllers\TwoFactorController;
use App\Contexts\Accounts\Profile\Http\Controllers\ProfileController;
use App\Contexts\Alliance\Content\Http\Controllers\ContentManagementController;
use App\Contexts\Alliance\Content\Http\Controllers\MemberContentController;
use App\Contexts\Alliance\Content\Http\Controllers\PublicAllianceController;
use App\Contexts\Alliance\Content\Http\Controllers\PublicBrandingMediaController;
use App\Contexts\Alliance\Content\Http\Controllers\PublicContentController;
use App\Contexts\Alliance\Lifecycle\Http\Controllers\CreateAllianceController;
use App\Contexts\Alliance\Membership\Http\Controllers\InvitationController;
use App\Contexts\Alliance\Membership\Http\Controllers\MembershipController;
use App\Contexts\Alliance\Recruitment\Http\Controllers\PublicRecruitmentController;
use App\Contexts\Alliance\Recruitment\Http\Controllers\RecruitmentCandidateController;
use App\Contexts\Alliance\Recruitment\Http\Controllers\RecruitmentManagementController;
use App\Contexts\GameWorld\Players\Http\Controllers\ActivatePlayerController;
use App\Contexts\Operations\BattlePlans\Http\Controllers\EventBattlePlanController;
use App\Contexts\Operations\Events\Http\Controllers\EventManagementController;
use App\Contexts\Operations\Events\Http\Controllers\EventOperationsController;
use App\Contexts\Operations\Participation\Http\Controllers\EventParticipationController;
use App\Contexts\Operations\Participation\Reminders\Http\Controllers\EventReminderController;
use App\Contexts\Operations\Rallies\Http\Controllers\EventRallyController;
use App\Contexts\Operations\Rallies\Http\Controllers\PlayerFormationController;
use App\Contexts\Operations\Rallies\Http\Controllers\RallyGuidanceController;
use App\Contexts\Operations\Results\Http\Controllers\EventResultController;
use App\Contexts\Operations\Rosters\Http\Controllers\EventRosterController;
use App\ReadModels\AllianceDashboard\Http\Controllers\AllianceOverviewController;
use App\ReadModels\AnnouncementBroadcastManagement\Http\Controllers\AnnouncementBroadcastManagementController;
use App\ReadModels\CommandOverview\Http\Controllers\DashboardController;
use App\ReadModels\EventCalendar\Http\Controllers\EventCalendarController;
use App\ReadModels\EventManagement\Http\Controllers\EventManagementPageController;
use App\ReadModels\RecruitmentDiscovery\Http\Controllers\PublicRecruitmentBoardController;
use App\ReadModels\RecruitmentManagement\Http\Controllers\RecruitmentCandidateReadController;
use App\ReadModels\RecruitmentManagement\Http\Controllers\RecruitmentManagementReadController;
use App\Workflows\AccountOnboarding\Http\Controllers\InvitationAcceptanceController;
use App\Workflows\AccountOnboarding\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', static fn () => Inertia::render('Public/Home', [
    'application' => [
        'name' => config('app.name'),
    ],
]))->name('home');

Route::get('/recruitment', PublicRecruitmentBoardController::class)
    ->name('public.recruitment.index');

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
        Route::post('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'accept'])
            ->where('token', '[A-Fa-f0-9]{64}')
            ->name('invitations.accept');

        Route::post('/players/{player}/activate', ActivatePlayerController::class)
            ->whereUlid('player')
            ->name('players.activate');

        Route::get('/events', [EventCalendarController::class, 'index'])->name('events.index');
        Route::get('/events/create', [EventManagementController::class, 'create'])->name('events.create');
        Route::get('/events/export.csv', [EventCalendarController::class, 'export'])->name('events.export');
        Route::get('/events/feed.ics', [EventCalendarController::class, 'ical'])->name('events.ical');
        Route::get('/events/{occurrence}', [EventCalendarController::class, 'show'])
            ->whereUlid('occurrence')
            ->name('events.show');
        Route::get('/events/{event}/manage', EventManagementPageController::class)
            ->whereUlid('event')
            ->name('events.management');

        Route::post('/events/{occurrence}/responses', [EventParticipationController::class, 'respond'])
            ->whereUlid('occurrence')
            ->name('events.responses.store');
        Route::post('/events/{occurrence}/registrations', [EventParticipationController::class, 'register'])
            ->whereUlid('occurrence')
            ->name('events.registrations.store');
        Route::delete('/events/{occurrence}/registrations', [EventParticipationController::class, 'cancelRegistration'])
            ->whereUlid('occurrence')
            ->name('events.registrations.cancel');
        Route::put('/events/{occurrence}/attendance/{player}', [EventParticipationController::class, 'attendance'])
            ->whereUlid('occurrence')
            ->whereUlid('player')
            ->name('events.attendance.update');
        Route::put('/events/{occurrence}/polls/{poll}/vote', [EventOperationsController::class, 'vote'])
            ->whereUlid('occurrence')
            ->whereUlid('poll')
            ->name('events.polls.vote');
        Route::put('/events/{occurrence}/roster-members/{member}/response', [EventRosterController::class, 'respond'])
            ->whereUlid('occurrence')
            ->whereUlid('member')
            ->name('events.roster-members.response');

        Route::post('/player/formations', [PlayerFormationController::class, 'store'])
            ->name('player.formations.store');
        Route::patch('/player/formations/{formation}', [PlayerFormationController::class, 'update'])
            ->whereUlid('formation')
            ->name('player.formations.update');
        Route::delete('/player/formations/{formation}', [PlayerFormationController::class, 'destroy'])
            ->whereUlid('formation')
            ->name('player.formations.destroy');
        Route::put('/events/{occurrence}/rally-assignments/{assignment}/response', [EventRallyController::class, 'respond'])
            ->whereUlid('occurrence')
            ->whereUlid('assignment')
            ->name('events.rally-assignments.response');

        Route::middleware('password.confirm')->group(function (): void {
            Route::post('/events', [EventManagementController::class, 'store'])->name('events.store');
            Route::post('/events/bulk-cancel/preview', [EventManagementController::class, 'previewBulkCancellation'])
                ->name('events.bulk-cancel.preview');
            Route::post('/events/bulk-cancel', [EventManagementController::class, 'commitBulkCancellation'])
                ->name('events.bulk-cancel.commit');
            Route::patch('/events/{event}', [EventManagementController::class, 'update'])
                ->whereUlid('event')
                ->name('events.update');
            Route::delete('/events/{event}', [EventManagementController::class, 'cancel'])
                ->whereUlid('event')
                ->name('events.cancel');
            Route::post('/event-templates', [EventManagementController::class, 'storeTemplate'])
                ->name('event-templates.store');
            Route::post('/event-templates/{template}/events', [EventManagementController::class, 'storeFromTemplate'])
                ->whereUlid('template')
                ->name('event-templates.events.store');
            Route::post('/events/{occurrence}/phases', [EventOperationsController::class, 'storePhase'])
                ->whereUlid('occurrence')
                ->name('events.phases.store');
            Route::patch('/events/{occurrence}/phases/{phase}', [EventOperationsController::class, 'updatePhase'])
                ->whereUlid('occurrence')
                ->whereUlid('phase')
                ->name('events.phases.update');
            Route::post('/events/{occurrence}/polls', [EventOperationsController::class, 'storePoll'])
                ->whereUlid('occurrence')
                ->name('events.polls.store');
            Route::patch('/events/{occurrence}/polls/{poll}', [EventOperationsController::class, 'updatePoll'])
                ->whereUlid('occurrence')
                ->whereUlid('poll')
                ->name('events.polls.update');
            Route::post('/events/{event}/reminders', [EventReminderController::class, 'store'])
                ->whereUlid('event')
                ->name('events.reminders.store');
            Route::delete('/events/{event}/reminders/{rule}', [EventReminderController::class, 'destroy'])
                ->whereUlid('event')
                ->whereUlid('rule')
                ->name('events.reminders.destroy');
            Route::post('/events/{occurrence}/rosters', [EventRosterController::class, 'store'])
                ->whereUlid('occurrence')
                ->name('events.rosters.store');
            Route::patch('/events/{occurrence}/rosters/{roster}', [EventRosterController::class, 'update'])
                ->whereUlid('occurrence')
                ->whereUlid('roster')
                ->name('events.rosters.update');
            Route::put('/events/{occurrence}/rosters/{roster}/players/{player}', [EventRosterController::class, 'assign'])
                ->whereUlid('occurrence')
                ->whereUlid('roster')
                ->whereUlid('player')
                ->name('events.rosters.players.assign');
            Route::delete('/events/{occurrence}/rosters/{roster}/players/{player}', [EventRosterController::class, 'remove'])
                ->whereUlid('occurrence')
                ->whereUlid('roster')
                ->whereUlid('player')
                ->name('events.rosters.players.remove');

            Route::post('/alliances/{alliance}/rally-guidance', [RallyGuidanceController::class, 'store'])
                ->whereUlid('alliance')
                ->name('alliances.rally-guidance.store');
            Route::patch('/alliances/{alliance}/rally-guidance/{rule}', [RallyGuidanceController::class, 'update'])
                ->whereUlid('alliance')
                ->whereUlid('rule')
                ->name('alliances.rally-guidance.update');
            Route::post('/events/{occurrence}/rally-formations', [EventRallyController::class, 'storeFormation'])
                ->whereUlid('occurrence')
                ->name('events.rally-formations.store');
            Route::patch('/events/{occurrence}/rally-formations/{formation}', [EventRallyController::class, 'updateFormation'])
                ->whereUlid('occurrence')
                ->whereUlid('formation')
                ->name('events.rally-formations.update');
            Route::post('/events/{occurrence}/rally-groups', [EventRallyController::class, 'storeGroup'])
                ->whereUlid('occurrence')
                ->name('events.rally-groups.store');
            Route::patch('/events/{occurrence}/rally-groups/{group}', [EventRallyController::class, 'updateGroup'])
                ->whereUlid('occurrence')
                ->whereUlid('group')
                ->name('events.rally-groups.update');
            Route::put('/events/{occurrence}/rally-groups/{group}/players/{player}', [EventRallyController::class, 'assign'])
                ->whereUlid('occurrence')
                ->whereUlid('group')
                ->whereUlid('player')
                ->name('events.rally-groups.players.assign');
            Route::delete('/events/{occurrence}/rally-groups/{group}/players/{player}', [EventRallyController::class, 'remove'])
                ->whereUlid('occurrence')
                ->whereUlid('group')
                ->whereUlid('player')
                ->name('events.rally-groups.players.remove');
            Route::patch('/events/{occurrence}/rally-assignments/{assignment}/participation', [EventRallyController::class, 'participation'])
                ->whereUlid('occurrence')
                ->whereUlid('assignment')
                ->name('events.rally-assignments.participation');
            Route::post('/events/{occurrence}/objectives', [EventBattlePlanController::class, 'storeObjective'])
                ->whereUlid('occurrence')
                ->name('events.objectives.store');
            Route::patch('/events/{occurrence}/objectives/{objective}', [EventBattlePlanController::class, 'updateObjective'])
                ->whereUlid('occurrence')
                ->whereUlid('objective')
                ->name('events.objectives.update');
            Route::put('/events/{occurrence}/objectives/{objective}/players/{player}', [EventBattlePlanController::class, 'assignPlayer'])
                ->whereUlid('occurrence')
                ->whereUlid('objective')
                ->whereUlid('player')
                ->name('events.objectives.players.assign');
            Route::put('/events/{occurrence}/objectives/{objective}/rosters/{roster}', [EventBattlePlanController::class, 'assignRoster'])
                ->whereUlid('occurrence')
                ->whereUlid('objective')
                ->whereUlid('roster')
                ->name('events.objectives.rosters.assign');
            Route::delete('/events/{occurrence}/objective-assignments/{assignment}', [EventBattlePlanController::class, 'removeAssignment'])
                ->whereUlid('occurrence')
                ->whereUlid('assignment')
                ->name('events.objective-assignments.destroy');
            Route::put('/events/{occurrence}/result', [EventResultController::class, 'saveOccurrence'])
                ->whereUlid('occurrence')
                ->name('events.results.update');
            Route::put('/events/{occurrence}/results/players/{player}', [EventResultController::class, 'savePlayer'])
                ->whereUlid('occurrence')
                ->whereUlid('player')
                ->name('events.player-results.update');
        });

        Route::middleware('alliance.context')->group(function (): void {
            Route::get('/alliance', AllianceOverviewController::class)->name('alliance.overview');

            Route::get('/alliance/recruitment', RecruitmentManagementReadController::class)
                ->name('alliance.recruitment.index');
            Route::get('/alliance/recruitment/{candidate}', RecruitmentCandidateReadController::class)
                ->whereUlid('candidate')
                ->name('alliance.recruitment.candidates.show');

            Route::get('/alliance/content', [MemberContentController::class, 'index'])
                ->name('alliance.content.index');
            Route::get('/alliance/content/manage', AnnouncementBroadcastManagementController::class)
                ->name('alliance.content.manage');
            Route::get('/alliance/content/{contentSlug}', [MemberContentController::class, 'show'])
                ->where('contentSlug', '[a-z0-9]+(?:-[a-z0-9]+)*')
                ->name('alliance.content.show');

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
                Route::post('/alliance/recruitment/bulk-stage/preview', [RecruitmentManagementController::class, 'previewBulkStageChange'])
                    ->name('alliance.recruitment.bulk-stage.preview');
                Route::post('/alliance/recruitment/bulk-stage', [RecruitmentManagementController::class, 'commitBulkStageChange'])
                    ->name('alliance.recruitment.bulk-stage.store');
                Route::patch('/alliance/recruitment/{candidate}/stage', [RecruitmentCandidateController::class, 'updateStage'])
                    ->whereUlid('candidate')
                    ->name('alliance.recruitment.candidates.stage.update');
                Route::put('/alliance/recruitment/{candidate}/reviewers/{player}', [RecruitmentCandidateController::class, 'assignReviewer'])
                    ->whereUlid('candidate')
                    ->whereUlid('player')
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
                Route::put('/alliance/content/{content}/broadcast-schedule', [ContentManagementController::class, 'saveBroadcastSchedule'])
                    ->whereUlid('content')
                    ->name('alliance.content.broadcast-schedule.update');
                Route::delete('/alliance/content/broadcast-schedules/{schedule}', [ContentManagementController::class, 'cancelBroadcastSchedule'])
                    ->whereUlid('schedule')
                    ->name('alliance.content.broadcast-schedule.cancel');
                Route::post('/alliance/content/{content}/broadcast-test', [ContentManagementController::class, 'testBroadcast'])
                    ->whereUlid('content')
                    ->name('alliance.content.broadcast-test.store');
                Route::post('/alliance/content/broadcast-runs/{run}/retry-failures', [ContentManagementController::class, 'retryBroadcastFailures'])
                    ->whereUlid('run')
                    ->name('alliance.content.broadcast-runs.retry-failures');

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

                Route::post('/alliance/memberships/bulk-status/preview', [MembershipController::class, 'previewBulkStatusChange'])
                    ->name('alliance.memberships.bulk-status.preview');
                Route::post('/alliance/memberships/bulk-status', [MembershipController::class, 'commitBulkStatusChange'])
                    ->name('alliance.memberships.bulk-status.commit');
                Route::patch('/alliance/memberships/{membership}/status', [MembershipController::class, 'updateStatus'])
                    ->whereUlid('membership')
                    ->name('alliance.memberships.status');
                Route::patch('/alliance/memberships/{membership}/rank', [MembershipController::class, 'updateRank'])
                    ->whereUlid('membership')
                    ->name('alliance.memberships.rank');
                Route::put('/alliance/memberships/{membership}/roles/{role}', [MembershipController::class, 'assignRole'])
                    ->whereUlid('membership')
                    ->whereUlid('role')
                    ->name('alliance.memberships.roles.assign');
                Route::delete('/alliance/memberships/{membership}/roles/{role}', [MembershipController::class, 'removeRole'])
                    ->whereUlid('membership')
                    ->whereUlid('role')
                    ->name('alliance.memberships.roles.remove');
                Route::post('/alliance/leadership/transfer', [MembershipController::class, 'transferLeadership'])
                    ->name('alliance.leadership.transfer');
                Route::delete('/alliance/membership', [MembershipController::class, 'leave'])
                    ->name('alliance.membership.leave');
            });
        });
    });
});
