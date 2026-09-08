<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Data\VerifiedAccountLogin;
use App\Contexts\Accounts\Authentication\Services\AccountLoginProofs;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\MfaLoginChallenge;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Validated;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use LogicException;
use SensitiveParameter;

final readonly class AuthenticateWithPassword
{
    public function __construct(
        private AccountLoginProofs $proofs,
        private MfaLoginChallenge $challenges,
        private CompleteAccountLogin $complete,
    ) {}

    // True means a second factor is required; the request remains a guest.
    public function handle(Request $request, string $email, #[SensitiveParameter] string $password, bool $remember, ?string $invitationToken, ?int $expectedUserId = null): bool
    {
        $guard = Auth::guard('web');
        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Password login requires the maintained web session guard.');
        }
        $credentials = ['email' => Str::lower(trim($email)), 'password' => $password];
        $proof = (new Timebox)->call(function (Timebox $timebox) use ($guard, $credentials, $remember, $expectedUserId): VerifiedAccountLogin {
            Event::dispatch(new Attempting('web', $credentials, $remember));
            $verified = DB::transaction(function () use ($guard, $credentials, $expectedUserId): ?VerifiedAccountLogin {
                $user = User::query()->where('email', $credentials['email'])->lockForUpdate()->first();
                if ($user === null || ($expectedUserId !== null && (int) $user->id !== $expectedUserId)
                    || ! $user->isActive() || ! $user->supportsPasswordAuthentication()
                    || ! $guard->getProvider()->validateCredentials($user, $credentials)) {
                    Event::dispatch(new Failed('web', $user, $credentials));

                    return null;
                }
                if ((bool) config('hashing.rehash_on_login', true)) {
                    $guard->getProvider()->rehashPasswordIfRequired($user, $credentials);
                }
                DB::afterCommit(static fn () => Event::dispatch(new Validated('web', $user)));

                return $this->proofs->password($user);
            });
            if ($verified === null) {
                throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
            }
            $timebox->returnEarly();

            return $verified;
        }, 200000);

        if ($proof->requiresMultiFactor) {
            $this->challenges->start($request, $proof, $remember, $invitationToken);

            return true;
        }
        $this->complete->handle($request, $proof, $remember);

        return false;
    }
}
