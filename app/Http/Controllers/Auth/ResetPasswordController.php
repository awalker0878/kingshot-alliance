<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ResetPasswordController extends Controller
{
    public function __construct(private AuditRecorder $audit) {}

    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            [
                'email' => Str::lower(trim((string) $validated['email'])),
                'password' => (string) $validated['password'],
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'token' => (string) $validated['token'],
            ],
            function ($user, string $password): void {
                if (! $user instanceof User) {
                    return;
                }

                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
                event(new PasswordReset($user));

                $this->audit->record(
                    event: 'auth.password.reset',
                    actor: $user,
                    subject: $user,
                );
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
