<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Http\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

final class ResetPasswordController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

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
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(12)->letters()->mixedCase()->numbers(),
            ],
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

                $currentUser = DB::transaction(function () use ($user, $password): User {
                    $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $locked->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();
                    $locked->tokens()->delete();

                    $this->audit->record(
                        event: 'auth.password.reset',
                        actor: $locked,
                        subject: $locked,
                    );

                    return $locked->refresh();
                });

                // Framework/event listeners run after our User-row transaction so mail or
                // other external side effects cannot extend the database lock lifetime.
                event(new PasswordReset($currentUser));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
