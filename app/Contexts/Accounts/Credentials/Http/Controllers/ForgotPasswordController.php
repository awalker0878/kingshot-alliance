<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class ForgotPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Accounts/Access/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user?->supportsPasswordAuthentication()) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with(
            'status',
            'If an account exists for that email address, a password reset link has been sent.',
        );
    }
}
