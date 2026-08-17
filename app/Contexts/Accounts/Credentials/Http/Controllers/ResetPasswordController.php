<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Http\Controllers;

use App\Contexts\Accounts\Credentials\Actions\ResetPassword;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

final class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Accounts/Access/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request, ResetPassword $resetPassword): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(12)->letters()->mixedCase()->numbers(),
            ],
        ]);

        $status = $resetPassword->handle(
            (string) $validated['email'],
            (string) $validated['password'],
            (string) $request->input('password_confirmation'),
            (string) $validated['token'],
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
