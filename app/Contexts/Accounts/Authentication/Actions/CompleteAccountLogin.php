<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Data\VerifiedAccountLogin;
use App\Contexts\Accounts\Authentication\Services\AccountLoginProofs;
use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\MfaLoginChallenge;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TwoFactorManager;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use SensitiveParameter;
use Throwable;

final readonly class CompleteAccountLogin
{
    public function __construct(
        private AccountLoginProofs $proofs,
        private TwoFactorManager $twoFactor,
        private RecordAccountSession $sessions,
        private AuditRecorder $audit,
        private RecentAuthentication $recentAuthentication,
        private MfaLoginChallenge $challenges,
    ) {}

    public function handle(
        Request $request,
        VerifiedAccountLogin $proof,
        bool $remember = false,
        #[SensitiveParameter] ?string $code = null,
        #[SensitiveParameter] ?string $recoveryCode = null,
    ): void {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Account login must run outside an enclosing database transaction.');
        }
        $guard = Auth::guard('web');
        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Account login requires the maintained web session guard.');
        }
        abort_if($guard->check(), 409, 'An authenticated account cannot start a new sign-in.');
        $errorKey = $proof->requiresMultiFactor ? 'code' : ($proof->method === 'google' ? 'google' : 'email');
        $mfaMethod = $proof->method === 'passkey' ? 'user_verifying_passkey' : null;
        $user = DB::transaction(function () use ($request, $proof, $remember, $code, $recoveryCode, $errorKey, &$mfaMethod): User {
            $user = $this->currentAccount($request, $proof, $errorKey);
            if ($proof->requiresMultiFactor) {
                if ($code !== null && $code !== '' && $this->twoFactor->verifyTotp($user, $code)) {
                    $mfaMethod = 'totp';
                } elseif ($recoveryCode !== null && $this->twoFactor->verifyRecoveryCode($user, $recoveryCode)) {
                    $mfaMethod = 'recovery_code';
                } else {
                    throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
                }
            }
            if ($remember && empty($user->getRememberToken())) {
                // Prepare only in memory. The maintained guard then avoids its
                // unlocked remember-token write; persistence belongs to completion.
                $user->setRememberToken(Str::random(60));
            }

            return $user;
        });

        $committed = false;
        try {
            Event::defer(function () use ($guard, $request, $user, $proof, $remember, $recoveryCode, $mfaMethod, $errorKey, &$committed): void {
                // Maintained fixation protection may destroy raw session storage.
                // Prepare it before opening the durable completion transaction.
                $guard->login($user, $remember);
                DB::transaction(function () use ($request, $user, $proof, $remember, $recoveryCode, $mfaMethod, $errorKey): void {
                    $current = $this->currentAccount($request, $proof, $errorKey);
                    if ($mfaMethod === 'recovery_code' && ! $this->twoFactor->consumeRecoveryCode($current, (string) $recoveryCode)) {
                        throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
                    }
                    if ($remember && empty($current->getRememberToken())) {
                        $preparedToken = $user->getRememberToken();
                        if ($preparedToken === null || $preparedToken === '') {
                            throw new LogicException('The prepared remember token is missing.');
                        }
                        $current->setRememberToken($preparedToken);
                        $current->save();
                    }
                    abort_unless($this->sessions->handle($proof->userId, $request->session()->getId(), (string) $request->userAgent()), 401);
                    $this->audit->record(event: 'auth.login', actor: $current, subject: $current,
                        metadata: ['provider' => $proof->method, 'mfa_method' => $mfaMethod]);
                });
                $committed = true;
            }, [Login::class, Authenticated::class]);
        } catch (Throwable $exception) {
            if (! $committed) {
                // A failed guard rotation may have stored the account ID before
                // setting its in-memory user. Do not let cleanup reload that ID
                // and emit an Authenticated event for an aborted preparation.
                $request->session()->forget($guard->getName());
                try {
                    $guard->logoutCurrentDevice();
                } catch (Throwable $cleanupFailure) {
                    report($cleanupFailure);
                } finally {
                    $guard->forgetUser();
                    $request->session()->forget($guard->getName());
                    $this->recentAuthentication->clear($request);
                    // Keep a still-valid MFA challenge retryable. The failed
                    // prepared ID never became an authoritative account session.
                    $request->session()->regenerate();
                }
                throw $exception;
            }
            // A framework event consumer failed after the durable completion.
            report($exception);
        }

        $this->challenges->clear($request);
        $this->recentAuthentication->mark($request, $proof->method, $proof->credentialReference);
    }

    private function currentAccount(Request $request, VerifiedAccountLogin $proof, string $errorKey): User
    {
        $user = User::query()->whereKey($proof->userId)->lockForUpdate()->first();
        if ($user === null || ! $this->proofs->matches($user, $proof)) {
            $this->challenges->clear($request);
            throw ValidationException::withMessages([$errorKey => 'This sign-in request expired or changed. Please sign in again.']);
        }

        return $user;
    }
}
