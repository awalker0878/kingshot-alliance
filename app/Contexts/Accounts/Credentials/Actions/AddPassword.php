<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Actions;

use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AddPassword
{
    public function __construct(
        private AuditRecorder $audit,
        private RevokeOtherAccountSessions $revokeOtherSessions,
        private SecurityNotificationService $securityNotifications,
    ) {}

    public function handle(int $userId, string $password, ?string $currentSessionId): void
    {
        DB::transaction(function () use ($userId, $password, $currentSessionId): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $user->ensureActive();

            if ($user->supportsPasswordAuthentication()) {
                throw ValidationException::withMessages([
                    'password' => 'A Kingshot Alliance password is already configured.',
                ]);
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $this->audit->record(
                event: 'account.password.added',
                actor: $user,
                subject: $user,
            );
            $this->revokeOtherSessions->handle($userId, $currentSessionId);
            $this->securityNotifications->publish(
                userId: $userId,
                event: 'account.password.added',
                title: (string) __('accounts.security.password_added.title'),
                body: (string) __('accounts.security.password_added.body'),
                idempotencyKey: 'account.password.added:'.$userId.':'.now()->format('Uu'),
            );
        });
    }
}
