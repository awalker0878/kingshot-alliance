<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Providers;

use App\Contexts\Alliance\Membership\Http\Controllers\BulkAllianceRankController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class MembershipServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'auth.session', 'verified', 'alliance.context', 'password.confirm'])
            ->group(function (): void {
                Route::post('/alliance/memberships/bulk-rank/preview', [BulkAllianceRankController::class, 'preview'])
                    ->name('alliance.memberships.bulk-rank.preview');
                Route::post('/alliance/memberships/bulk-rank', [BulkAllianceRankController::class, 'commit'])
                    ->name('alliance.memberships.bulk-rank.commit');
            });
    }
}
