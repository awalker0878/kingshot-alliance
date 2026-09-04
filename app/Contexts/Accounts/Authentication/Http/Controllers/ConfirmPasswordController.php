<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Controllers;

use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ConfirmPasswordController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly RecentAuthentication $recentAuthentication,
    ) {}

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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User && $user->supportsPasswordAuthentication(), 403);

        $passwordHash = $user->getAuthPassword();

        if (! is_string($passwordHash) || $passwordHash === '' || ! Hash::check((string) $validated['password'], $passwordHash)) {
            throw ValidationException::withMessages([
                'password' => 'The provided password is incorrect.',
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());
        $this->recentAuthentication->mark($request, 'password');

        $this->audit->record(
            event: 'auth.password.confirmed',
            actor: $user,
            subject: $user,
        );

        return redirect()->intended(route('dashboard'));
    }
}
