<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Data\VerifiedAccountLogin;
use App\Contexts\Accounts\Authentication\Services\AccountLoginProofs;
use App\Contexts\Accounts\Identity\Actions\RecordAccountIdentityUse;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\MfaLoginChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class AuthenticateWithGoogle
{
    public function __construct(
        private RecordAccountIdentityUse $recordIdentityUse,
        private AccountLoginProofs $proofs,
        private MfaLoginChallenge $challenges,
        private CompleteAccountLogin $complete,
    ) {}

    // Socialite's verified subject is immutable input; routing snapshots grant no authority.
    public function handle(Request $request, int $userId, int $identityId, string $verifiedSubject, string $verifiedEmail, ?string $invitationToken): bool
    {
        $proof = DB::transaction(function () use ($userId, $identityId, $verifiedSubject, $verifiedEmail): VerifiedAccountLogin {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $user->ensureActive();
            $identity = AccountIdentity::query()->whereKey($identityId)->where('user_id', $userId)
                ->where('provider', 'google')->lockForUpdate()->first();
            abort_unless($identity !== null && hash_equals($identity->provider_subject, $verifiedSubject), 403,
                'This Google sign-in request changed. Please sign in again.');
            $this->recordIdentityUse->handle($identityId, $verifiedEmail, true);

            return $this->proofs->google($user, $identity);
        });

        if ($proof->requiresMultiFactor) {
            $this->challenges->start($request, $proof, false, $invitationToken);

            return true;
        }
        $this->complete->handle($request, $proof);

        return false;
    }
}
