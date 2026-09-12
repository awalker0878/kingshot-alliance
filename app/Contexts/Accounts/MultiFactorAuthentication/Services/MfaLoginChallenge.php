<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\MultiFactorAuthentication\Services;

use App\Contexts\Accounts\Authentication\Data\VerifiedAccountLogin;
use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use Illuminate\Http\Request;

/**
 * @phpstan-type MfaLoginState array{user_id:int,method:'password'|'google',credential_id:?string,primary_fingerprint:string,account_fingerprint:string,issued_at:int,remember:bool,invitation_token:?string}
 */
final readonly class MfaLoginChallenge
{
    private const KEY = 'accounts.mfa_login';

    private const TTL_SECONDS = 600;

    public function __construct(private RecentAuthentication $recentAuthentication) {}

    public function start(Request $request, VerifiedAccountLogin $proof, bool $remember, ?string $invitationToken): void
    {
        abort_unless($proof->requiresMultiFactor && in_array($proof->method, ['password', 'google'], true), 403);
        $this->recentAuthentication->clear($request);
        $request->session()->put(self::KEY, [
            'user_id' => $proof->userId,
            'method' => $proof->method,
            'credential_id' => $proof->credentialReference,
            'primary_fingerprint' => $proof->credentialFingerprint,
            'account_fingerprint' => $proof->accountFingerprint,
            'issued_at' => (int) now()->timestamp,
            'remember' => $remember,
            'invitation_token' => $invitationToken,
        ]);
    }

    /** @return MfaLoginState|null */
    public function pending(Request $request): ?array
    {
        $state = $request->session()->get(self::KEY);
        if (! is_array($state)) {
            return null;
        }
        $userId = $state['user_id'] ?? null;
        $method = $state['method'] ?? null;
        $credentialId = $state['credential_id'] ?? null;
        $primaryFingerprint = $state['primary_fingerprint'] ?? null;
        $accountFingerprint = $state['account_fingerprint'] ?? null;
        $issuedAt = $state['issued_at'] ?? null;
        $remember = $state['remember'] ?? null;
        $invitationToken = $state['invitation_token'] ?? null;
        $now = (int) now()->timestamp;

        if (! is_int($userId) || $userId <= 0 || ! in_array($method, ['password', 'google'], true)
            || ($method === 'google' && (! is_string($credentialId) || ! ctype_digit($credentialId) || (int) $credentialId <= 0))
            || ($method === 'password' && $credentialId !== null)
            || ! is_string($primaryFingerprint) || ! preg_match('/^[a-f0-9]{64}$/D', $primaryFingerprint)
            || ! is_string($accountFingerprint) || ! preg_match('/^[a-f0-9]{64}$/D', $accountFingerprint)
            || ! is_int($issuedAt) || $issuedAt > $now || $issuedAt <= $now - self::TTL_SECONDS
            || ! is_bool($remember) || ($invitationToken !== null && ! is_string($invitationToken))) {
            $this->clear($request);

            return null;
        }

        return [
            'user_id' => $userId,
            'method' => $method,
            'credential_id' => $credentialId,
            'primary_fingerprint' => $primaryFingerprint,
            'account_fingerprint' => $accountFingerprint,
            'issued_at' => $issuedAt,
            'remember' => $remember,
            'invitation_token' => $invitationToken,
        ];
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }
}
