<?php

declare(strict_types=1);

namespace App\Workflows\Registration\Http\Controllers;

use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\Registration\Actions\RegisterAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class RegistrationController extends Controller
{
    public function create(Request $request, FindPendingInvitation $invitations): Response
    {
        $token = trim((string) $request->query('invitation', ''));
        $invitation = $token === '' ? null : $invitations->byToken($token);

        return Inertia::render('Auth/Register', [
            'registrationMode' => config('accounts.registration_mode'),
            'invitationToken' => $invitation instanceof Invitation ? $token : null,
            'invitedEmail' => $invitation?->email,
            'invitedAllianceName' => $invitation?->alliance?->name,
        ]);
    }

    public function store(
        Request $request,
        FindPendingInvitation $invitations,
        RegisterAccount $registerAccount,
    ): RedirectResponse {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'invitation_token' => trim((string) $request->input('invitation_token', '')),
        ]);

        $mode = (string) config('accounts.registration_mode', 'open');
        $token = (string) $request->input('invitation_token');
        $invitation = $token === '' ? null : $invitations->byToken($token);

        if ($mode !== 'open' && ! $invitation instanceof Invitation) {
            abort(403, 'A valid invitation is required to register.');
        }

        if ($token !== '' && ! $invitation instanceof Invitation) {
            abort(404);
        }

        if ($invitation instanceof Invitation && ! hash_equals(
            Str::lower((string) $invitation->email),
            (string) $request->input('email'),
        )) {
            throw ValidationException::withMessages([
                'email' => 'Use the email address that received this invitation.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
            'timezone' => ['required', 'string', 'timezone'],
            'invitation_token' => ['nullable', 'string', 'max:256'],
        ]);

        $result = $registerAccount->handle(
            name: (string) $validated['name'],
            email: (string) $validated['email'],
            password: (string) $validated['password'],
            timezone: (string) $validated['timezone'],
            invitation: $invitation,
            invitationToken: $token === '' ? null : $token,
        );

        Auth::login($result['user']);
        $request->session()->regenerate();

        if ($result['membership'] instanceof AllianceMembership) {
            $request->session()->put(
                (string) config('game_world.active_player_session_key'),
                (string) $result['membership']->player_id,
            );

            return redirect()->route('alliance.overview');
        }

        return redirect()->route('verification.notice');
    }
}
