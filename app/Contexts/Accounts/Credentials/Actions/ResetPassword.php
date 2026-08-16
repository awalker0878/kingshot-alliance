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
        return Password::reset(
            [
                'email' => Str::lower(trim($email)),
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
                'token' => $token,
            ],
            function ($user, string $newPassword): void {
                if (! $user instanceof User) {
                    return;
                }

                $currentUser = DB::transaction(function () use ($user, $newPassword): User {
                    $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
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

                event(new PasswordResetEvent($currentUser));
            },
        );
    }
}
