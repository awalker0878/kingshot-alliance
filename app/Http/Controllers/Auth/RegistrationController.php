<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\AcceptInvitation;
use App\Application\Identity\FindPendingInvitation;
use App\Application\Identity\RegisterUser;
use App\Http\Controllers\Controller;
use App\Models\Alliance;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'registrationMode' => config('identity.registration_mode'),
            'invitationToken' => $invitation instanceof Invitation ? $token : null,
            'invitedEmail' => $invitation?->email,
            'invitedAllianceName' => $invitation?->alliance?->name,
        ]);
    }

    public function store(
        Request $request,
        RegisterUser $register,
        FindPendingInvitation $invitations,
        AcceptInvitation $acceptInvitation,
    ): RedirectResponse {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'invitation_token' => trim((string) $request->input('invitation_token', '')),
        ]);

        $mode = (string) config('identity.registration_mode', 'open');
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

        /** @var array{user: User, alliance: Alliance|null} $result */
        $result = DB::transaction(function () use ($validated, $register, $invitation, $acceptInvitation): array {
            $user = $register->handle(
                name: $validated['name'],
                email: $validated['email'],
                password: $validated['password'],
                timezone: $validated['timezone'],
            );

            $alliance = $invitation instanceof Invitation
                ? $acceptInvitation->handle($user, $validated['invitation_token'])
                : null;

            return [
                'user' => $user,
                'alliance' => $alliance,
            ];
        });

        Auth::login($result['user']);
        $request->session()->regenerate();
        $result['user']->sendEmailVerificationNotification();

        if ($result['alliance'] instanceof Alliance) {
            $request->session()->put(
                (string) config('identity.active_alliance_session_key'),
                $result['alliance']->id,
            );

            return redirect()->route('alliance.overview');
        }

        return redirect()->route('verification.notice');
    }
}
