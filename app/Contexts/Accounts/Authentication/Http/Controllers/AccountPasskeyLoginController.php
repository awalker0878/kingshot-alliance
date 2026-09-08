<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Controllers;

use App\Contexts\Accounts\Authentication\Actions\CompleteAccountLogin;
use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Authentication\Services\AccountLoginProofs;
use App\Contexts\Accounts\Identity\Models\User;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Passkeys;
use LogicException;

final class AccountPasskeyLoginController extends PasskeyLoginController
{
    public function __construct(private readonly CompleteAccountLogin $complete, private readonly AccountLoginProofs $proofs) {}

    public function store(PasskeyVerificationRequest $request, VerifyPasskey $verify): PasskeyLoginResponse
    {
        if (config('passkeys.guard') !== 'web') {
            throw new LogicException('Accounts passkeys require the web session guard.');
        }
        $passkey = $verify($request->credential(), $request->verificationOptions());
        if (! $passkey instanceof AccountPasskey || ! $passkey->user instanceof User || ! Passkeys::allowsLogin($request, $passkey)) {
            throw InvalidPasskeyException::make('Unable to sign in with this account.');
        }

        $this->complete->handle($request, $this->proofs->passkey($passkey->user, $passkey), $request->remember());

        return app(PasskeyLoginResponse::class);
    }
}
