<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;

final class StoreAccountPasskey extends StorePasskey
{
    public function __invoke(
        Authenticatable $user,
        string $name,
        PublicKeyCredential $credential,
        PublicKeyCredentialCreationOptions $options,
    ): Passkey {
        abort_unless($user instanceof User, 403);

        return DB::transaction(function () use ($user, $name, $credential, $options): Passkey {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedUser->ensureActive();

            // The maintained Action owns WebAuthn validation, creation and its event.
            return parent::__invoke($lockedUser, $name, $credential, $options);
        });
    }

    protected function validate(AuthenticatorAttestationResponse $response, PublicKeyCredentialCreationOptions $options): CredentialRecord
    {
        try {
            return parent::validate($response, $options);
        } catch (AuthenticatorResponseVerificationException) {
            throw InvalidPasskeyException::make('Unable to register passkey. Please try again.');
        }
    }
}
