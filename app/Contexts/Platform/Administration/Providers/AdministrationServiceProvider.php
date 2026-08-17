<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Providers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

final class AdministrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Horizon::auth(static function (): bool {
            $request = app()->bound('request') ? request() : null;
            $principal = $request instanceof Request ? $request->user() : null;
            $identifier = $principal?->getAuthIdentifier();

            if (! is_numeric($identifier)) {
                return false;
            }

            $account = app(AccountIdentityQuery::class)->find((int) $identifier);

            return $account !== null
                && $account->emailVerified
                && $account->multiFactorConfirmed
                && PlatformAdministrator::activeForUserId($account->userId);
        });
    }
}
