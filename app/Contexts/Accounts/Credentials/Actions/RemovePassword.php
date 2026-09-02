<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Actions;

use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RemovePassword
{
    public function __construct(
        private AccountSignInMethodPolicy $methods,
        private AuditRecorder $audit,
        private SecurityNotificationService $securityNotifications,
    ) {}

    public function handle(int $userId): void
    {
        $email = DB::transaction(function () use ($userId): string {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            if (! $this->methods->canRemovePassword($user)) {
                throw ValidationException::withMessages([
                    'password' => 'Add another sign-in method before removing your Kingshot Alliance password.',
                ]);
            }

            $user->forceFill([
                'password' => null,
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();

            $this->audit->record(
                event: 'account.password.removed',
                actor: $user,
                subject: $user,
            );

            return (string) $user->email;
        });

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $this->securityNotifications->publish(
            userId: $userId,
            event: 'account.password.removed',
            title: (string) __('accounts.security.password_removed.title'),
            body: (string) __('accounts.security.password_removed.body'),
            idempotencyKey: 'account.password.removed:'.$userId.':'.now()->format('Uu'),
        );
    }
}
