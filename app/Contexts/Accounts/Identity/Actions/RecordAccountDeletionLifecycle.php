<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RecordAccountDeletionLifecycle
{
    public function __construct(
        private AuditRecorder $audit,
        private SecurityNotificationService $securityNotifications,
    ) {}

    public function requested(int $userId, string $requestId): void
    {
        DB::transaction(function () use ($userId, $requestId): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $user->forceFill(['deletion_requested_at' => now()])->save();
            $this->audit->record(
                event: 'account.deletion_requested',
                actor: $user,
                subject: $user,
                metadata: ['deletion_request_id' => $requestId],
            );
        });

        $this->securityNotifications->publish(
            userId: $userId,
            event: 'account.deletion_requested',
            title: (string) __('accounts.security.deletion_requested.title'),
            body: (string) __('accounts.security.deletion_requested.body'),
            idempotencyKey: 'account.deletion_requested:'.$requestId,
        );
    }

    public function cancelled(int $userId, string $requestId): void
    {
        DB::transaction(function () use ($userId, $requestId): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $user->forceFill(['deletion_requested_at' => null])->save();
            $this->audit->record(
                event: 'account.deletion_cancelled',
                actor: $user,
                subject: $user,
                metadata: ['deletion_request_id' => $requestId],
            );
        });

        $this->securityNotifications->publish(
            userId: $userId,
            event: 'account.deletion_cancelled',
            title: (string) __('accounts.security.deletion_cancelled.title'),
            body: (string) __('accounts.security.deletion_cancelled.body'),
            idempotencyKey: 'account.deletion_cancelled:'.$requestId.':'.now()->format('Uu'),
        );
    }
}
