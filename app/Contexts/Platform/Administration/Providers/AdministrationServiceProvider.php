<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Providers;

use App\Contexts\Accounts\Identity\Models\User;
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
            $user = $request instanceof Request ? $request->user() : null;

            return $user instanceof User
                && $user->hasVerifiedEmail()
                && $user->two_factor_confirmed_at !== null
                && PlatformAdministrator::activeFor($user);
        });
    }
}
