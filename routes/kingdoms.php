<?php

declare(strict_types=1);

use App\Domain\Kingdoms\Http\Controllers\KingdomAllianceController;
use App\Domain\Kingdoms\Http\Controllers\KingdomAllianceDiplomacyContactController;
use App\Domain\Kingdoms\Http\Controllers\KingdomAllianceDiplomacyController;
use App\Domain\Kingdoms\Http\Controllers\KingdomAllianceObservationController;
use App\Domain\Kingdoms\Http\Controllers\KingdomSettingsController;
use App\Domain\Kingdoms\Http\Controllers\PlayerSnapshotController;
use App\Domain\Kingdoms\Http\Controllers\RosterController;
use App\Domain\Kingdoms\Http\Controllers\RosterCsvController;
use App\Domain\Kingdoms\Http\Controllers\RosterIntelligenceController;
use App\Domain\Kingdoms\Http\Controllers\TransferCompletionController;
use App\Domain\Kingdoms\Http\Controllers\TransferGroupController;
use App\Domain\Kingdoms\Http\Controllers\TransferParticipantController;
use App\Domain\Kingdoms\Http\Controllers\TransferPlanController;
use App\Domain\Kingdoms\Http\Controllers\TransferReadinessController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'alliance.context'])->group(function (): void {
    Route::get('/alliance/settings/kingdom', [KingdomSettingsController::class, 'index'])
        ->name('alliance.kingdom.edit');

    Route::patch('/alliance/settings/kingdom', [KingdomSettingsController::class, 'update'])
        ->middleware('password.confirm')
        ->name('alliance.kingdom.update');

    Route::get('/alliance/roster', [RosterController::class, 'index'])
        ->name('alliance.roster.index');

    Route::get('/alliance/roster/manage', [RosterController::class, 'manage'])
        ->name('alliance.roster.manage');

    Route::get('/alliance/roster/intelligence', [RosterIntelligenceController::class, 'index'])
        ->name('alliance.roster.intelligence');

    Route::get('/alliance/roster/import', [RosterCsvController::class, 'index'])
        ->name('alliance.roster.import.index');
    Route::get('/alliance/roster/import/{import}', [RosterCsvController::class, 'show'])
        ->name('alliance.roster.import.show');
    Route::get('/alliance/roster/export.csv', [RosterCsvController::class, 'export'])
        ->name('alliance.roster.export');

    Route::get('/alliance/roster/{entry}/history', [PlayerSnapshotController::class, 'show'])
        ->name('alliance.roster.history');

    Route::get('/alliance/kingdom-alliances', [KingdomAllianceController::class, 'index'])
        ->name('alliance.kingdom-alliances.index');
    Route::get('/alliance/kingdom-alliances/manage', [KingdomAllianceController::class, 'manage'])
        ->name('alliance.kingdom-alliances.manage');
    Route::get('/alliance/kingdom-alliances/{tracking}/history', [KingdomAllianceObservationController::class, 'show'])
        ->name('alliance.kingdom-alliances.history');
    Route::get('/alliance/kingdom-alliances/{tracking}/diplomacy', [KingdomAllianceDiplomacyController::class, 'show'])
        ->name('alliance.kingdom-alliances.diplomacy.show');
    Route::get(
        '/alliance/kingdom-alliances/{tracking}/diplomacy/contacts',
        [KingdomAllianceDiplomacyContactController::class, 'show'],
    )->name('alliance.kingdom-alliances.diplomacy.contacts.show');

    Route::get('/alliance/transfers', [TransferPlanController::class, 'index'])
        ->name('alliance.transfers.index');
    Route::get('/alliance/transfers/manage', [TransferPlanController::class, 'manage'])
        ->name('alliance.transfers.manage');
    Route::get('/alliance/transfers/readiness', [TransferReadinessController::class, 'index'])
        ->name('alliance.transfers.readiness');
    Route::get('/alliance/transfers/completion', [TransferCompletionController::class, 'index'])
        ->name('alliance.transfers.completion');

    Route::middleware('password.confirm')->group(function (): void {
        Route::post('/alliance/roster', [RosterController::class, 'store'])
            ->name('alliance.roster.store');
        Route::patch('/alliance/roster/{entry}', [RosterController::class, 'update'])
            ->name('alliance.roster.update');
        Route::post('/alliance/roster/{entry}/leave', [RosterController::class, 'leave'])
            ->name('alliance.roster.leave');
        Route::post('/alliance/roster/{entry}/snapshots', [PlayerSnapshotController::class, 'store'])
            ->name('alliance.roster.snapshots.store');
        Route::post('/alliance/roster/import/preview', [RosterCsvController::class, 'preview'])
            ->name('alliance.roster.import.preview');
        Route::post('/alliance/roster/import/{import}/commit', [RosterCsvController::class, 'commit'])
            ->name('alliance.roster.import.commit');

        Route::post('/alliance/kingdom-alliances', [KingdomAllianceController::class, 'store'])
            ->name('alliance.kingdom-alliances.store');
        Route::patch('/alliance/kingdom-alliances/{tracking}', [KingdomAllianceController::class, 'update'])
            ->name('alliance.kingdom-alliances.update');
        Route::post('/alliance/kingdom-alliances/{tracking}/archive', [KingdomAllianceController::class, 'archive'])
            ->name('alliance.kingdom-alliances.archive');
        Route::post('/alliance/kingdom-alliances/{tracking}/observations', [KingdomAllianceObservationController::class, 'store'])
            ->name('alliance.kingdom-alliances.observations.store');
        Route::post(
            '/alliance/kingdom-alliances/{tracking}/observations/{observation}/invalidate',
            [KingdomAllianceObservationController::class, 'invalidate'],
        )->name('alliance.kingdom-alliances.observations.invalidate');
        Route::post(
            '/alliance/kingdom-alliances/{tracking}/diplomacy/transitions',
            [KingdomAllianceDiplomacyController::class, 'transition'],
        )->name('alliance.kingdom-alliances.diplomacy.transition');
        Route::post(
            '/alliance/kingdom-alliances/{tracking}/diplomacy/contacts',
            [KingdomAllianceDiplomacyContactController::class, 'store'],
        )->name('alliance.kingdom-alliances.diplomacy.contacts.store');
        Route::patch(
            '/alliance/kingdom-alliances/{tracking}/diplomacy/contacts/{contact}',
            [KingdomAllianceDiplomacyContactController::class, 'update'],
        )->name('alliance.kingdom-alliances.diplomacy.contacts.update');
        Route::post(
            '/alliance/kingdom-alliances/{tracking}/diplomacy/contacts/{contact}/deactivate',
            [KingdomAllianceDiplomacyContactController::class, 'deactivate'],
        )->name('alliance.kingdom-alliances.diplomacy.contacts.deactivate');

        Route::post('/alliance/transfers', [TransferPlanController::class, 'store'])
            ->name('alliance.transfers.store');
        Route::post('/alliance/transfers/{plan}/open', [TransferPlanController::class, 'open'])
            ->name('alliance.transfers.open');
        Route::post('/alliance/transfers/{plan}/lock', [TransferPlanController::class, 'lock'])
            ->name('alliance.transfers.lock');
        Route::post('/alliance/transfers/{plan}/close', [TransferPlanController::class, 'close'])
            ->name('alliance.transfers.close');
        Route::post('/alliance/transfers/{plan}/cancel', [TransferPlanController::class, 'cancel'])
            ->name('alliance.transfers.cancel');

        Route::post('/alliance/transfers/{plan}/groups', [TransferGroupController::class, 'store'])
            ->name('alliance.transfers.groups.store');
        Route::patch('/alliance/transfers/{plan}/groups/{group}', [TransferGroupController::class, 'update'])
            ->name('alliance.transfers.groups.update');
        Route::post('/alliance/transfers/{plan}/groups/{group}/archive', [TransferGroupController::class, 'archive'])
            ->name('alliance.transfers.groups.archive');

        Route::post('/alliance/transfers/{plan}/participants', [TransferParticipantController::class, 'store'])
            ->name('alliance.transfers.participants.store');
        Route::patch(
            '/alliance/transfers/{plan}/participants/{participant}',
            [TransferParticipantController::class, 'update'],
        )->name('alliance.transfers.participants.update');
        Route::patch(
            '/alliance/transfers/{plan}/participants/{participant}/group',
            [TransferGroupController::class, 'assignParticipant'],
        )->name('alliance.transfers.participants.group');
        Route::patch(
            '/alliance/transfers/{plan}/participants/{participant}/readiness',
            [TransferReadinessController::class, 'transition'],
        )->name('alliance.transfers.participants.readiness');
        Route::post(
            '/alliance/transfers/{plan}/participants/{participant}/blockers',
            [TransferReadinessController::class, 'storeBlocker'],
        )->name('alliance.transfers.participants.blockers.store');
        Route::post(
            '/alliance/transfers/{plan}/participants/{participant}/blockers/{blocker}/resolve',
            [TransferReadinessController::class, 'resolveBlocker'],
        )->name('alliance.transfers.participants.blockers.resolve');
        Route::post(
            '/alliance/transfers/{plan}/participants/{participant}/withdraw',
            [TransferParticipantController::class, 'withdraw'],
        )->name('alliance.transfers.participants.withdraw');
        Route::post(
            '/alliance/transfers/{plan}/participants/{participant}/complete',
            [TransferCompletionController::class, 'store'],
        )->name('alliance.transfers.participants.complete');
    });
});
