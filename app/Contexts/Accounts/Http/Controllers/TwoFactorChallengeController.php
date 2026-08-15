<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Accounts\Services\TwoFactorManager;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Http\Controller;
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

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(
        Request $request,
        TwoFactorManager $twoFactor,
        AuditRecorder $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:32'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ]);

        $userId = $request->session()->get('accounts.two_factor_challenge_user_id');
        abort_unless(is_int($userId), 403);

        $user = User::query()->findOrFail($userId);
        abort_if($user->two_factor_confirmed_at === null, 403);

        $code = trim((string) ($validated['code'] ?? ''));
        $recoveryCode = trim((string) ($validated['recovery_code'] ?? ''));
        $method = null;

        if ($code !== '' && $twoFactor->verifyTotp($user, $code)) {
            $method = 'totp';
        } elseif ($recoveryCode !== '' && $twoFactor->consumeRecoveryCode($user, $recoveryCode)) {
            $method = 'recovery_code';
        }

        if ($method === null) {
            throw ValidationException::withMessages([
                'code' => 'The authentication code is invalid.',
            ]);
        }

        $remember = (bool) $request->session()->pull('accounts.two_factor_remember', false);
        $invitationToken = trim((string) $request->session()->pull('accounts.two_factor_invitation_token', ''));
        $request->session()->forget('accounts.two_factor_challenge_user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $audit->record(
            event: 'auth.login',
            actor: $user,
            subject: $user,
            metadata: ['mfa_method' => $method],
        );

        if ($invitationToken !== '') {
            return redirect()->route('invitations.show', ['token' => $invitationToken]);
        }

        return redirect()->intended(route('dashboard'));
    }
}
