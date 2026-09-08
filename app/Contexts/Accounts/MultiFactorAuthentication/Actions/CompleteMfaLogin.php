<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\MultiFactorAuthentication\Actions;

use App\Contexts\Accounts\Authentication\Actions\CompleteAccountLogin;
use App\Contexts\Accounts\Authentication\Data\VerifiedAccountLogin;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\MfaLoginChallenge;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

final readonly class CompleteMfaLogin
{
    public function __construct(private MfaLoginChallenge $challenges, private CompleteAccountLogin $complete) {}

    public function handle(Request $request, #[SensitiveParameter] string $code, #[SensitiveParameter] string $recoveryCode): ?string
    {
        $state = $this->challenges->pending($request);
        if ($state === null) {
            throw ValidationException::withMessages(['code' => 'This sign-in request expired or changed. Please sign in again.']);
        }
        $proof = new VerifiedAccountLogin($state['user_id'], $state['method'], $state['credential_id'],
            $state['primary_fingerprint'], $state['account_fingerprint'], true);
        $this->complete->handle($request, $proof, $state['remember'], $code, $recoveryCode);

        return $state['invitation_token'];
    }
}
