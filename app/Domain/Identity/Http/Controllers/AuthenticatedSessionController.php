<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Actions\AcceptInvitation;
use App\Domain\Memberships\Models\Invitation;
use App\Domain\Memberships\Queries\FindPendingInvitation;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'invitationToken' => trim((string) $request->query('invitation', '')) ?: null,
        ]);
    }

    public function store(
        Request $request,
        AuditRecorder $audit,
        FindPendingInvitation $invitations,
        AcceptInvitation $acceptInvitation,
    ): RedirectResponse {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
            'invitation_token' => ['nullable', 'string', 'max:256'],
        ]);

        $remember = (bool) ($validated['remember'] ?? false);
        $authenticated = Auth::attempt([
            'email' => Str::lower(trim($validated['email'])),
            'password' => $validated['password'],
        ], $remember);

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();
        abort_unless($user instanceof User, 500);

        $token = trim((string) ($validated['invitation_token'] ?? ''));

        if ($user->two_factor_confirmed_at !== null && (string) $user->two_factor_secret !== '') {
            $request->session()->put([
                'identity.two_factor_challenge_user_id' => $user->id,
                'identity.two_factor_remember' => $remember,
                'identity.two_factor_invitation_token' => $token,
            ]);

            Auth::guard('web')->logout();
            $request->session()->regenerate();

            return redirect()->route('two-factor.login');
        }

        $audit->record(
            event: 'auth.login',
            actor: $user,
            subject: $user,
            metadata: ['mfa_method' => null],
        );

        if ($token !== '') {
            $invitation = $invitations->byToken($token);

            if ($invitation instanceof Invitation && hash_equals(
                Str::lower((string) $invitation->email),
                Str::lower((string) $user->email),
            )) {
                $membership = $acceptInvitation->handle($user, $token);
                $request->session()->put(
                    (string) config('identity.active_player_session_key'),
                    (string) $membership->player_id,
                );

                return redirect()->route('alliance.overview');
            }

            return redirect()->route('invitations.show', ['token' => $token]);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $audit->record(
                event: 'auth.logout',
                actor: $user,
                subject: $user,
            );
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
