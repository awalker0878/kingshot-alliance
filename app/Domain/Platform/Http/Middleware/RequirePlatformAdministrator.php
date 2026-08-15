<?php

declare(strict_types=1);

namespace App\Domain\Platform\Http\Middleware;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Platform\Access\Models\PlatformAdministrator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePlatformAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->hasVerifiedEmail(), 403, 'Platform administrators must have a verified email address.');
        abort_unless($user->two_factor_confirmed_at !== null, 403, 'Platform administrators must enable multi-factor authentication.');
        abort_unless(PlatformAdministrator::activeFor($user), 403, 'Platform administrator access is required.');

        $request->attributes->set('platform_administrator_user_id', $user->id);

        return $next($request);
    }
}
