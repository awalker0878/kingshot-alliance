<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Services;

use Illuminate\Http\Request;

final class RecentAuthentication
{
    private const AT = 'accounts.recent_authentication_at';
    private const METHOD = 'accounts.recent_authentication_method';
    private const CREDENTIAL = 'accounts.recent_authentication_credential';

    public function mark(Request $request, string $method, ?string $credentialReference = null): void
    {
        $request->session()->put([
            self::AT => now()->timestamp,
            self::METHOD => $method,
            self::CREDENTIAL => $credentialReference,
        ]);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget([
            self::AT,
            self::METHOD,
            self::CREDENTIAL,
            'auth.password_confirmed_at',
            'accounts.google_reauthenticated_at',
        ]);
    }

    public function isSatisfied(Request $request): bool
    {
        $timeout = max(1, (int) config('auth.password_timeout', 10800));
        $threshold = now()->timestamp - $timeout;
        $confirmedAt = (int) $request->session()->get(self::AT, 0);

        if ($confirmedAt >= $threshold) {
            return true;
        }

        // Transitional aliases are accepted while existing routes/tests are reconciled.
        $legacyConfirmedAt = max(
            (int) $request->session()->get('auth.password_confirmed_at', 0),
            (int) $request->session()->get('accounts.google_reauthenticated_at', 0),
        );

        return $legacyConfirmedAt >= $threshold;
    }
}
