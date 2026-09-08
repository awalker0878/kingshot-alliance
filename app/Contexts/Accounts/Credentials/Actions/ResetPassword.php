<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Actions;

use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use LogicException;

final readonly class ResetPassword
{
    public function __construct(
        private AuditRecorder $audit,
        private RevokeOtherAccountSessions $revokeOtherSessions,
        private SecurityNotificationService $securityNotifications,
    ) {}

    public function handle(
        string $email,
        string $password,
        string $passwordConfirmation,
        string $token,
    ): string {
        $normalizedEmail = Str::lower(trim($email));

        return DB::transaction(function () use ($normalizedEmail, $password, $passwordConfirmation, $token): string {
            $locked = User::query()->where('email', $normalizedEmail)->lockForUpdate()->first();
            if ($locked === null || $locked->anonymized_at !== null || ! $locked->supportsPasswordAuthentication()) {
                return Password::INVALID_USER;
            }

            // The maintained broker owns token hashing/expiry and consumption.
            // Its entire check/callback/delete sequence shares the account lock.
            return Password::reset(
                [
                    'email' => $normalizedEmail,
                    'password' => $password,
                    'password_confirmation' => $passwordConfirmation,
                    'token' => $token,
                ],
                function ($user, string $newPassword) use ($locked): void {
                    if (! $user instanceof User || (int) $user->id !== (int) $locked->id) {
                        throw new LogicException('Password broker resolved a different account.');
                    }

                    $locked->forceFill(['password' => Hash::make($newPassword)])->save();
                    $locked->tokens()->delete();
                    $this->audit->record(
                        event: 'auth.password.reset',
                        actor: $locked,
                        subject: $locked,
                    );
                    $this->revokeOtherSessions->handle((int) $locked->id, null);
                    $this->securityNotifications->publish(
                        userId: (int) $locked->id,
                        event: 'auth.password.reset',
                        title: (string) __('accounts.security.password_changed.title'),
                        body: (string) __('accounts.security.password_changed.body'),
                        idempotencyKey: 'auth.password.reset:'.$locked->id.':'.Str::ulid(),
                    );

                    DB::afterCommit(static function () use ($locked): void {
                        event(new PasswordResetEvent($locked));
                    });
                },
            );
        });
    }
}
