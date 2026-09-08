<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\MultiFactorAuthentication\Services;

use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Http\Request;

/**
 * @phpstan-type MfaLoginState array{user_id:int,method:'password'|'google',credential_id:?int,primary_fingerprint:string,mfa_fingerprint:string,issued_at:int,remember:bool,invitation_token:?string}
 */
final readonly class MfaLoginChallenge
{
    private const KEY = 'accounts.mfa_login';

    private const TTL_SECONDS = 600;

    public function __construct(private RecentAuthentication $recentAuthentication) {}

    public function startPassword(Request $request, User $verifiedUser, bool $remember, ?string $invitationToken): void
    {
        abort_unless($verifiedUser->supportsPasswordAuthentication(), 403);
        $this->start($request, $verifiedUser, 'password', null,
            hash('sha256', (string) $verifiedUser->getAuthPassword()), $remember, $invitationToken);
    }

    public function startGoogle(Request $request, int $userId, int $verifiedIdentityId, ?string $invitationToken): void
    {
        $user = User::query()->findOrFail($userId);
        $identity = AccountIdentity::query()->whereKey($verifiedIdentityId)
            ->where('user_id', $userId)->where('provider', 'google')->firstOrFail();
        $this->start($request, $user, 'google', (int) $identity->id,
            hash('sha256', $identity->provider_subject), false, $invitationToken);
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
        $mfaFingerprint = $state['mfa_fingerprint'] ?? null;
        $issuedAt = $state['issued_at'] ?? null;
        $remember = $state['remember'] ?? null;
        $invitationToken = $state['invitation_token'] ?? null;
        $now = (int) now()->timestamp;

        if (! is_int($userId) || $userId <= 0 || ! in_array($method, ['password', 'google'], true)
            || ($credentialId !== null && (! is_int($credentialId) || $credentialId <= 0))
            || ($method === 'google' && $credentialId === null)
            || ($method === 'password' && $credentialId !== null)
            || ! is_string($primaryFingerprint) || strlen($primaryFingerprint) !== 64
            || ! is_string($mfaFingerprint) || strlen($mfaFingerprint) !== 64
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
            'mfa_fingerprint' => $mfaFingerprint,
            'issued_at' => $issuedAt,
            'remember' => $remember,
            'invitation_token' => $invitationToken,
        ];
    }

    /** @param MfaLoginState $state */
    public function matchesCurrentCredentials(User $user, array $state): bool
    {
        if ((int) $user->id !== $state['user_id'] || $user->anonymized_at !== null
            || $user->two_factor_confirmed_at === null || (string) $user->two_factor_secret === ''
            || ! hash_equals($state['mfa_fingerprint'], hash('sha256', (string) $user->two_factor_secret))) {
            return false;
        }

        if ($state['method'] === 'password') {
            return $user->supportsPasswordAuthentication()
                && hash_equals($state['primary_fingerprint'], hash('sha256', (string) $user->getAuthPassword()));
        }

        $identity = AccountIdentity::query()->whereKey($state['credential_id'])
            ->where('user_id', $user->id)->where('provider', 'google')->first();

        return $identity !== null && hash_equals($state['primary_fingerprint'], hash('sha256', $identity->provider_subject));
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }

    /** @param 'password'|'google' $method */
    private function start(Request $request, User $user, string $method, ?int $credentialId, string $primaryFingerprint, bool $remember, ?string $invitationToken): void
    {
        abort_unless($user->anonymized_at === null && $user->two_factor_confirmed_at !== null
            && (string) $user->two_factor_secret !== '', 403);
        $this->recentAuthentication->clear($request);
        $request->session()->put(self::KEY, [
            'user_id' => (int) $user->id,
            'method' => $method,
            'credential_id' => $credentialId,
            'primary_fingerprint' => $primaryFingerprint,
            'mfa_fingerprint' => hash('sha256', (string) $user->two_factor_secret),
            'issued_at' => (int) now()->timestamp,
            'remember' => $remember,
            'invitation_token' => $invitationToken,
        ]);
    }
}
