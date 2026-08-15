<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Http\Controllers;

use App\Contexts\Accounts\Actions\RegisterUser;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class RegistrationController extends Controller
{
    public function create(Request $request): Response
    {
        $token = trim((string) $request->query('invitation', ''));

        return Inertia::render('Auth/Register', [
            'registrationMode' => config('accounts.registration_mode'),
            'invitationToken' => $token === '' ? null : $token,
            'invitedEmail' => null,
            'invitedAllianceName' => null,
        ]);
    }

    public function store(Request $request, RegisterUser $register): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'invitation_token' => trim((string) $request->input('invitation_token', '')),
        ]);

        $mode = (string) config('accounts.registration_mode', 'open');
        if ($mode !== 'open') {
            abort(403, 'Registration is currently invitation-only.');
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

        $user = $register->handle(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            timezone: $validated['timezone'],
        );

        Auth::login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();

        $token = trim((string) ($validated['invitation_token'] ?? ''));
        if ($token !== '') {
            return redirect()->route('invitations.show', ['token' => $token]);
        }

        return redirect()->route('verification.notice');
    }
}
