<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $authenticated = Auth::attempt([
            'email' => Str::lower(trim($validated['email'])),
            'password' => $validated['password'],
        ], (bool) ($validated['remember'] ?? false));

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user instanceof User) {
            $audit->record(
                event: 'auth.login',
                actor: $user,
                subject: $user,
            );
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
