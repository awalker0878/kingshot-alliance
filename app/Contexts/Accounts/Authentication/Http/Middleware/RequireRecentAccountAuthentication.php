<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Middleware;

use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireRecentAccountAuthentication
{
    public function __construct(
        private AccountSignInMethodPolicy $methods,
        private RecentAuthentication $recentAuthentication,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($this->recentAuthentication->isSatisfied($request)) {
            return $next($request);
        }

        if ($this->methods->hasPassword($user)) {
            return redirect()->guest(route('password.confirm'));
        }

        if ($this->methods->hasGoogle($user)) {
            return redirect()->guest(route('auth.google.reauthenticate'));
        }

        if ($this->methods->passkeyCount($user) > 0) {
            return redirect()->guest(route('account.confirm'));
        }

        abort(403, 'This Kingshot Alliance account does not have a usable sign-in method.');
    }
}
