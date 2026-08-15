<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Http\Controllers;

use App\Shared\Audit\Services\AuditRecorder;
use App\Contexts\Accounts\Models\User;
use App\Shared\Http\Controller;
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
                'accounts.two_factor_challenge_user_id' => $user->id,
                'accounts.two_factor_remember' => $remember,
                'accounts.two_factor_invitation_token' => $token,
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
