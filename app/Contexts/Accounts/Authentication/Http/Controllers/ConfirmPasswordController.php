<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Controllers;

use App\Contexts\Accounts\Authentication\Actions\ConfirmAccountPassword;
use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ConfirmPasswordController extends Controller
{
    public function create(Request $request, AccountSignInMethodPolicy $methods): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $summary = $methods->summary($user);

        return Inertia::render('Accounts/Access/ConfirmPassword', [
            'methods' => [
                'password' => $summary['password'],
                'google' => $summary['google'],
                'passkey' => $summary['passkeys'] > 0,
            ],
            'googleAuthEnabled' => filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret'))
                && filled(config('services.google.redirect')),
        ]);
    }

    public function store(Request $request, ConfirmAccountPassword $confirmPassword): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $confirmPassword->handle($request, (string) $validated['password']);

        return redirect()->intended(route('dashboard'));
    }
}
