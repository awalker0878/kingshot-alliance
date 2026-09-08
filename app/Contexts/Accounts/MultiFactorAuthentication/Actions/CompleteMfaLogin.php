<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\MultiFactorAuthentication\Actions;

use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\MfaLoginChallenge;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TwoFactorManager;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CompleteMfaLogin
{
    public function __construct(
        private MfaLoginChallenge $challenges,
        private TwoFactorManager $twoFactor,
        private RecentAuthentication $recentAuthentication,
        private RecordAccountSession $sessions,
        private AuditRecorder $audit,
    ) {}

    public function handle(Request $request, string $code, string $recoveryCode): ?string
    {
        return DB::transaction(function () use ($request, $code, $recoveryCode): ?string {
            $state = $this->challenges->pending($request);
            $user = $state === null ? null : User::query()->whereKey($state['user_id'])->lockForUpdate()->first();
            if ($state === null || $user === null || ! $this->challenges->matchesCurrentCredentials($user, $state)) {
                $this->challenges->clear($request);

                throw ValidationException::withMessages(['code' => 'This sign-in request expired or changed. Please sign in again.']);
            }

            $mfaMethod = null;
            if ($code !== '' && $this->twoFactor->verifyTotp($user, $code)) {
                $mfaMethod = 'totp';
            } elseif ($recoveryCode !== '' && $this->twoFactor->consumeRecoveryCode($user, $recoveryCode)) {
                $mfaMethod = 'recovery_code';
            }
            if ($mfaMethod === null) {
                throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
            }

            $this->challenges->clear($request);
            Auth::login($user, $state['remember']);
            $request->session()->regenerate();
            abort_unless($this->sessions->handle((int) $user->id, $request->session()->getId(), (string) $request->userAgent()), 401);
            $this->recentAuthentication->mark($request, $state['method'],
                $state['credential_id'] === null ? null : (string) $state['credential_id']);
            $this->audit->record(
                event: 'auth.login', actor: $user, subject: $user,
                metadata: ['provider' => $state['method'], 'mfa_method' => $mfaMethod],
            );

            return $state['invitation_token'];
        });
    }
}
