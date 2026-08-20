<?php

declare(strict_types=1);

use App\Contexts\Intelligence\Contributions\Http\Controllers\ContributionController;
use App\ReadModels\ContributionHistory\Http\Controllers\ContributionHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/contributions/history', [ContributionHistoryController::class, 'index'])
        ->name('contributions.history');
});

Route::middleware(['auth', 'auth.session', 'verified', 'alliance.context'])->group(function (): void {
    Route::get('/alliance/contributions', [ContributionController::class, 'index'])
        ->name('alliance.contributions.index');
    Route::get('/alliance/contributions/manage', [ContributionController::class, 'manage'])
        ->name('alliance.contributions.manage');
    Route::post('/alliance/contributions/self-report', [ContributionController::class, 'storeSelfReport'])
        ->middleware('throttle:20,1')
        ->name('alliance.contributions.self-report.store');

    Route::middleware('password.confirm')->group(function (): void {
        Route::post('/alliance/contributions/categories', [ContributionController::class, 'storeCategory'])
            ->name('alliance.contributions.categories.store');
        Route::post('/alliance/contributions/records', [ContributionController::class, 'storeManualRecord'])
            ->name('alliance.contributions.records.store');
        Route::post('/alliance/contributions/records/bulk-approve/preview', [ContributionController::class, 'previewBulkApproval'])
            ->name('alliance.contributions.records.bulk-approve.preview');
        Route::post('/alliance/contributions/records/bulk-approve', [ContributionController::class, 'commitBulkApproval'])
            ->name('alliance.contributions.records.bulk-approve.commit');
        Route::patch('/alliance/contributions/records/{record}/approve', [ContributionController::class, 'approve'])
            ->whereUlid('record')
            ->name('alliance.contributions.records.approve');
        Route::post('/alliance/contributions/records/{record}/correct', [ContributionController::class, 'correct'])
            ->whereUlid('record')
            ->name('alliance.contributions.records.correct');
        Route::patch('/alliance/contributions/records/{record}/reverse', [ContributionController::class, 'reverse'])
            ->whereUlid('record')
            ->name('alliance.contributions.records.reverse');
        Route::post('/alliance/contributions/data-quality/refresh', [ContributionController::class, 'refreshQuality'])
            ->name('alliance.contributions.data-quality.refresh');
        Route::patch('/alliance/contributions/data-quality/{flag}/resolve', [ContributionController::class, 'resolveQualityFlag'])
            ->whereUlid('flag')
            ->name('alliance.contributions.data-quality.resolve');
        Route::post('/alliance/contributions/report-schedules', [ContributionController::class, 'storeReportSchedule'])
            ->name('alliance.contributions.report-schedules.store');
        Route::get('/alliance/contributions/export.csv', [ContributionController::class, 'exportCsv'])
            ->middleware('throttle:10,1')
            ->name('alliance.contributions.export.csv');
        Route::get('/alliance/contributions/export.xls', [ContributionController::class, 'exportSpreadsheet'])
            ->middleware('throttle:10,1')
            ->name('alliance.contributions.export.spreadsheet');
    });
});
