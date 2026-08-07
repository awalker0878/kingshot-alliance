<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\AcceptInvitation;
use App\Application\Identity\AuditRecorder;
use App\Application\Identity\FindPendingInvitation;
use App\Application\Identity\TwoFactorManager;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! is_int($request->session()->get('identity.two_factor_challenge_user_id'))) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(
        Request $request,
        TwoFactorManager $twoFactor,
        AuditRecorder $audit,
        FindPendingInvitation $invitations,
        AcceptInvitation $acceptInvitation,
    ): RedirectResponse {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:32'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ]);

        $userId = $request->session()->get('identity.two_factor_challenge_user_id');
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

        $remember = (bool) $request->session()->pull('identity.two_factor_remember', false);
        $invitationToken = trim((string) $request->session()->pull('identity.two_factor_invitation_token', ''));
        $request->session()->forget('identity.two_factor_challenge_user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $audit->record(
            event: 'auth.login',
            actor: $user,
            subject: $user,
            metadata: ['mfa_method' => $method],
        );

        if ($invitationToken !== '') {
            $invitation = $invitations->byToken($invitationToken);

            if ($invitation instanceof Invitation && hash_equals(
                Str::lower((string) $invitation->email),
                Str::lower((string) $user->email),
            )) {
                $alliance = $acceptInvitation->handle($user, $invitationToken);
                $request->session()->put(
                    (string) config('identity.active_alliance_session_key'),
                    $alliance->id,
                );

                return redirect()->route('alliance.overview');
            }

            return redirect()->route('invitations.show', ['token' => $invitationToken]);
        }

        return redirect()->intended(route('dashboard'));
    }
}
