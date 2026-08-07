<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\RegisterUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class RegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'registrationMode' => config('identity.registration_mode'),
        ]);
    }

    public function store(Request $request, RegisterUser $register): RedirectResponse
    {
        abort_unless(config('identity.registration_mode') === 'open', 403);

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $user = $register->handle(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            timezone: $validated['timezone'],
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
