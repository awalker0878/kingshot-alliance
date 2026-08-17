<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Profile\Actions\AuthorizeOtherSessionRevocation;
use App\Contexts\Accounts\Profile\Actions\ChangePassword;
use App\Contexts\Accounts\Profile\Actions\UpdateProfile;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('Accounts/Governor/Profile', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'emailVerified' => $user->hasVerifiedEmail(),
                'timezone' => $user->timezone,
                'twoFactorEnabled' => $user->two_factor_confirmed_at !== null,
                'twoFactorPending' => $user->two_factor_confirmed_at === null
                    && (string) $user->two_factor_secret !== '',
            ],
            'twoFactorSetup' => $request->session()->get('twoFactorSetup'),
            'twoFactorRecoveryCodes' => $request->session()->get('twoFactorRecoveryCodes'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(Request $request, UpdateProfile $updateProfile): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $emailChanged = $updateProfile->handle(
            (int) $user->id,
            (string) $validated['name'],
            (string) $validated['email'],
            (string) $validated['timezone'],
        );

        return $emailChanged
            ? redirect()->route('verification.notice')
            : redirect()->route('profile.show');
    }

    public function updatePassword(Request $request, ChangePassword $changePassword): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
        ]);

        $newPassword = (string) $validated['password'];
        $changePassword->handle(
            (int) $user->id,
            (string) $validated['current_password'],
            $newPassword,
        );

        return redirect()->route('profile.show')->with('status', 'password-updated');
    }

    public function destroyOtherSessions(
        Request $request,
        AuthorizeOtherSessionRevocation $authorizeRevocation,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);
        $password = (string) $validated['password'];
        $authorizeRevocation->handle((int) $user->id, $password);

        return redirect()->route('profile.show')->with('status', 'other-sessions-revoked');
    }
}
