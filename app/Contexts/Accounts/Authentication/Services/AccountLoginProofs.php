<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Services;

use App\Contexts\Accounts\Authentication\Data\VerifiedAccountLogin;
use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;

final class AccountLoginProofs
{
    public function password(User $verifiedUser): VerifiedAccountLogin
    {
        return new VerifiedAccountLogin((int) $verifiedUser->id, 'password', null,
            hash('sha256', (string) $verifiedUser->getAuthPassword()), $this->accountFingerprint($verifiedUser),
            $this->requiresMultiFactor($verifiedUser));
    }

    public function google(User $verifiedUser, AccountIdentity $identity): VerifiedAccountLogin
    {
        return new VerifiedAccountLogin((int) $verifiedUser->id, 'google', (string) $identity->id,
            hash('sha256', $identity->provider_subject), $this->accountFingerprint($verifiedUser),
            $this->requiresMultiFactor($verifiedUser));
    }

    public function passkey(User $verifiedUser, AccountPasskey $passkey): VerifiedAccountLogin
    {
        return new VerifiedAccountLogin((int) $verifiedUser->id, 'passkey', (string) $passkey->public_id,
            $this->passkeyFingerprint($passkey), $this->accountFingerprint($verifiedUser), false);
    }

    // Call with the current account lock held. Selected credentials cannot be
    // changed by another owner while that account lock is held.
    public function matches(User $current, VerifiedAccountLogin $proof): bool
    {
        if ((int) $current->id !== $proof->userId || ! $current->isActive()
            || ! hash_equals($proof->accountFingerprint, $this->accountFingerprint($current))) {
            return false;
        }
        if ($proof->method === 'password') {
            return $current->supportsPasswordAuthentication()
                && hash_equals($proof->credentialFingerprint, hash('sha256', (string) $current->getAuthPassword()));
        }
        if ($proof->method === 'google') {
            $identity = AccountIdentity::query()->whereKey($proof->credentialReference)
                ->where('user_id', $current->id)->where('provider', 'google')->first();

            return $identity !== null && hash_equals($proof->credentialFingerprint, hash('sha256', $identity->provider_subject));
        }
        $passkey = AccountPasskey::query()->where('public_id', $proof->credentialReference)->where('user_id', $current->id)->first();

        return $passkey !== null && hash_equals($proof->credentialFingerprint, $this->passkeyFingerprint($passkey));
    }

    // The verified snapshot comes only from the maintained recaller validator.
    // Call with the current account lock held, before registering its session.
    public function matchesRemembered(User $current, User $verified): bool
    {
        return (int) $current->id === (int) $verified->id && $current->isActive()
            && $current->getRememberToken() !== ''
            && hash_equals($this->accountFingerprint($current), $this->accountFingerprint($verified));
    }

    private function requiresMultiFactor(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && (string) $user->two_factor_secret !== '';
    }

    private function accountFingerprint(User $user): string
    {
        // Existing authoritative credential/revocation state, never a second
        // mutable authentication version or clear recovery material in session.
        return hash('sha256', json_encode([
            $user->getAuthPassword(), $user->getRememberToken(),
            $user->two_factor_secret, $user->two_factor_confirmed_at?->toISOString(),
        ], JSON_THROW_ON_ERROR));
    }

    private function passkeyFingerprint(AccountPasskey $passkey): string
    {
        return hash('sha256', json_encode([
            $passkey->credential_id, $passkey->credential['credentialPublicKey'] ?? null,
            $passkey->credential['userHandle'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }
}
