<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ConfirmPasswordController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function create(): Response
    {
        return Inertia::render('Auth/ConfirmPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (! Hash::check((string) $validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The provided password is incorrect.',
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        $this->audit->record(
            event: 'auth.password.confirmed',
            actor: $user,
            subject: $user,
        );

        return redirect()->intended(route('dashboard'));
    }
}
