<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\MultiFactorAuthentication\Http\Controllers;

use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TwoFactorManager;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! is_int($request->session()->get('accounts.two_factor_challenge_user_id'))) {
            return redirect()->route('login');
        }

        return Inertia::render('Accounts/Access/TwoFactorChallenge');
    }

    public function store(
        Request $request,
        TwoFactorManager $twoFactor,
        AuditRecorder $audit,
        RecentAuthentication $recentAuthentication,
    ): RedirectResponse {
        $validated = $request->validate(['code' => ['nullable', 'string', 'max:32'], 'recovery_code' => ['nullable', 'string', 'max:64']]);
        $userId = $request->session()->get('accounts.two_factor_challenge_user_id');
        abort_unless(is_int($userId), 403);
        $user = User::query()->findOrFail($userId);
        abort_if($user->two_factor_confirmed_at === null, 403);
        $code = trim((string) ($validated['code'] ?? ''));
        $recoveryCode = trim((string) ($validated['recovery_code'] ?? ''));
        $mfaMethod = null;
        if ($code !== '' && $twoFactor->verifyTotp($user, $code)) {
            $mfaMethod = 'totp';
        } elseif ($recoveryCode !== '' && $twoFactor->consumeRecoveryCode($user, $recoveryCode)) {
            $mfaMethod = 'recovery_code';
        }
        if ($mfaMethod === null) {
            throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
        }
        $remember = (bool) $request->session()->pull('accounts.two_factor_remember', false);
        $invitationToken = trim((string) $request->session()->pull('accounts.two_factor_invitation_token', ''));
        $primaryMethod = trim((string) $request->session()->pull('accounts.two_factor_primary_method', ''));
        $request->session()->forget('accounts.two_factor_challenge_user_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        if (in_array($primaryMethod, ['password', 'google'], true)) {
            $recentAuthentication->mark($request, $primaryMethod);
        }

        $audit->record(
            event: 'auth.login',
            actor: $user,
            subject: $user,
            metadata: [
                'provider' => $primaryMethod === '' ? null : $primaryMethod,
                'mfa_method' => $mfaMethod,
            ],
        );
        if ($invitationToken !== '') {
            return redirect()->route('invitations.show', ['token' => $invitationToken]);
        }

        return redirect()->intended(route('dashboard'));
    }
}
