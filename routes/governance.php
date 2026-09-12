<?php

declare(strict_types=1);

use App\Contexts\GameWorld\Governance\Http\Controllers\KingdomGovernanceAdministrationController;
use App\ReadModels\KingdomGovernance\Http\Controllers\KingdomGovernanceAuthorityController;
use App\ReadModels\KingdomGovernance\Http\Controllers\KingdomGovernanceHealthController;
use App\ReadModels\KingdomGovernance\Http\Controllers\KingdomGovernanceHistoryController;
use App\ReadModels\KingdomGovernance\Http\Controllers\KingdomRoleManagementController;
use App\ReadModels\PlatformAdministration\Http\Controllers\PlatformKingdomGovernanceRecoveryReadController;
use App\Workflows\KingdomGovernance\Http\Controllers\KingdomGovernanceWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'alliance.context'])->group(function (): void {
    Route::get('/alliance/settings/kingdom/governance/choices/{kind}', [KingdomRoleManagementController::class, 'choices'])->whereIn('kind', ['players', 'roles'])->name('alliance.kingdom.governance.choices');
    Route::get('/alliance/settings/kingdom/governance/authority', KingdomGovernanceAuthorityController::class)->name('alliance.kingdom.governance.authority');
    Route::get('/alliance/settings/kingdom/governance/history', KingdomGovernanceHistoryController::class)->name('alliance.kingdom.governance.history');
    Route::get('/alliance/settings/kingdom/governance/health', KingdomGovernanceHealthController::class)->name('alliance.kingdom.governance.health');

    Route::middleware('password.confirm')->group(function (): void {
        Route::post('/alliance/settings/kingdom/governance/roles', [KingdomGovernanceAdministrationController::class, 'createRole'])->name('alliance.kingdom.governance.roles.store');
        Route::put('/alliance/settings/kingdom/governance/roles/{role}', [KingdomGovernanceAdministrationController::class, 'updateRole'])->whereUlid('role')->name('alliance.kingdom.governance.roles.update');
        Route::post('/alliance/settings/kingdom/governance/roles/{role}/archive', [KingdomGovernanceAdministrationController::class, 'archiveRole'])->whereUlid('role')->name('alliance.kingdom.governance.roles.archive');
        Route::post('/alliance/settings/kingdom/governance/administrator-handoff', [KingdomGovernanceAdministrationController::class, 'handoff'])->name('alliance.kingdom.governance.handoff');
        Route::post('/alliance/settings/kingdom/governance/bulk/preview', [KingdomGovernanceAdministrationController::class, 'bulkPreview'])->name('alliance.kingdom.governance.bulk.preview');
        Route::post('/alliance/settings/kingdom/governance/bulk', [KingdomGovernanceAdministrationController::class, 'bulk'])->name('alliance.kingdom.governance.bulk.store');
        Route::post('/alliance/settings/kingdom/governance/reconcile', [KingdomGovernanceWorkflowController::class, 'reconcile'])->name('alliance.kingdom.governance.reconcile');
    });
});

Route::middleware(['auth', 'auth.session', 'verified', 'platform.admin', 'password.confirm'])->prefix('platform')->name('platform.')->group(function (): void {
    Route::get('/kingdom-governance-recovery/choices/{kind}', [PlatformKingdomGovernanceRecoveryReadController::class, 'choices'])->whereIn('kind', ['kingdoms', 'players'])->name('kingdom-governance-recovery.choices');
    Route::get('/kingdom-governance-recovery', PlatformKingdomGovernanceRecoveryReadController::class)->name('kingdom-governance-recovery.index');
    Route::post('/kingdom-governance-recovery', [KingdomGovernanceWorkflowController::class, 'recover'])->name('kingdom-governance-recovery.store');
});
