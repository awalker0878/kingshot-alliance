<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceGovernance\Providers;

use App\ReadModels\AllianceGovernance\Http\Controllers\AllianceGovernanceController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AllianceGovernanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'auth.session', 'verified', 'alliance.context'])
            ->group(function (): void {
                Route::get('/alliance/history', [AllianceGovernanceController::class, 'index'])
                    ->name('alliance.history.index');
                Route::get('/alliance/members/{player}/history', [AllianceGovernanceController::class, 'member'])
                    ->whereUlid('player')
                    ->name('alliance.members.history');
                Route::get('/alliance/roster/reconciliation', [AllianceGovernanceController::class, 'reconciliation'])
                    ->name('alliance.roster.reconciliation');
            });
    }
}
