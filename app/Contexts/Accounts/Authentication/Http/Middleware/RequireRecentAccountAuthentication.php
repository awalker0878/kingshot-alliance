<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Middleware;

use App\Contexts\Accounts\Identity\Enums\AuthenticationType;
use App\Contexts\Accounts\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRecentAccountAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $timeout = max(1, (int) config('auth.password_timeout', 10800));
        $threshold = (int) now()->timestamp - $timeout;

        if ($user->authentication_type === AuthenticationType::Password) {
            $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

            if ($confirmedAt >= $threshold) {
                return $next($request);
            }

            return redirect()->guest(route('password.confirm'));
        }

        if ($user->authentication_type === AuthenticationType::Google) {
            $confirmedAt = (int) $request->session()->get('accounts.google_reauthenticated_at', 0);

            if ($confirmedAt >= $threshold) {
                return $next($request);
            }

            return redirect()->guest(route('auth.google.reauthenticate'));
        }

        abort(403, 'The account authentication type is not supported.');
    }
}
