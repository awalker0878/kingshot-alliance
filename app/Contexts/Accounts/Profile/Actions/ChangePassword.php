<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Actions;

use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ChangePassword
{
    public function __construct(
        private AuditRecorder $audit,
        private RevokeOtherAccountSessions $revokeOtherSessions,
        private SecurityNotificationService $securityNotifications,
    ) {}

    public function handle(int $userId, string $currentPassword, string $newPassword, ?string $currentSessionId): void
    {
        DB::transaction(function () use ($userId, $currentPassword, $newPassword, $currentSessionId): void {
            $locked = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $locked->ensureActive();

            if (! $locked->supportsPasswordAuthentication()) {
                throw ValidationException::withMessages([
                    'current_password' => 'This account does not have a local password.',
                ]);
            }

            if (! Hash::check($currentPassword, (string) $locked->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'The password is incorrect.',
                ]);
            }

            Password::deleteToken($locked);
            $locked->forceFill([
                'password' => Hash::make($newPassword),
                'remember_token' => Str::random(60),
            ])->save();
            $locked->tokens()->delete();

            $this->audit->record(
                event: 'profile.password.updated',
                actor: $locked,
                subject: $locked,
            );

            OutboxMessage::query()->create([
                'alliance_id' => null,
                'event_type' => 'profile.password.updated',
                'aggregate_type' => User::class,
                'aggregate_id' => (string) $locked->id,
                'idempotency_key' => 'profile.password.updated:'.$locked->id.':'.now()->format('Uu'),
                'payload' => ['user_id' => $locked->id],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);
            $this->revokeOtherSessions->handle($userId, $currentSessionId);
            $this->securityNotifications->publish(
                userId: $userId,
                event: 'profile.password.updated',
                title: (string) __('accounts.security.password_changed.title'),
                body: (string) __('accounts.security.password_changed.body'),
                idempotencyKey: 'profile.password.updated:'.$userId.':'.now()->format('Uu'),
            );
        });
    }
}
