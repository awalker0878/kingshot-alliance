<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\Exception\CounterException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

final class VerifyAccountPasskey extends VerifyPasskey
{
    public function __invoke(PublicKeyCredential $credential, PublicKeyCredentialRequestOptions $options, ?PasskeyUser $user = null): Passkey
    {
        $candidate = $this->getPasskey($credential);
        $this->ensurePasskeyBelongsToUser($candidate, $user);

        return DB::transaction(function () use ($candidate, $credential, $options): Passkey {
            $account = User::query()->whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
            $account->ensureActive();

            // The maintained verifier reselects/locks this account's credential,
            // validates its assertion and counter, and persists its event atomically.
            return parent::__invoke($credential, $options, $account);
        });
    }

    protected function validate(AuthenticatorAssertionResponse $response, Passkey $passkey, PublicKeyCredentialRequestOptions $options): CredentialRecord
    {
        try {
            return parent::validate($response, $passkey, $options);
        } catch (AuthenticatorResponseVerificationException|CounterException) {
            throw InvalidPasskeyException::make('Unable to verify passkey. Please try again.');
        }
    }
}
