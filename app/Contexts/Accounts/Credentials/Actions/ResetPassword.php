<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final readonly class ResetPassword
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        string $email,
        string $password,
        string $passwordConfirmation,
        string $token,
    ): string {
        $normalizedEmail = Str::lower(trim($email));
        $user = User::query()->where('email', $normalizedEmail)->first();

        if (! $user?->supportsPasswordAuthentication()) {
            return Password::INVALID_USER;
        }

        return Password::reset(
            [
                'email' => $normalizedEmail,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
                'token' => $token,
            ],
            function ($user, string $newPassword): void {
                if (! $user instanceof User || ! $user->supportsPasswordAuthentication()) {
                    return;
                }

                $currentUser = DB::transaction(function () use ($user, $newPassword): User {
                    $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                    if (! $locked->supportsPasswordAuthentication()) {
                        return $locked;
                    }

                    $locked->forceFill([
                        'password' => Hash::make($newPassword),
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

                if ($currentUser->supportsPasswordAuthentication()) {
                    event(new PasswordResetEvent($currentUser));
                }
            },
        );
    }
}
