<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RenameAccountPasskey
{
    public function __construct(
        private AuditRecorder $audit,
        private SecurityNotificationService $securityNotifications,
    ) {}

    public function handle(int $userId, string $publicId, string $name): void
    {
        DB::transaction(function () use ($userId, $publicId, $name): void {
            $user = User::query()->whereKey($userId)->firstOrFail();
            $passkey = AccountPasskey::query()
                ->where('user_id', $userId)
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            $passkey->forceFill(['name' => $name])->save();

            $this->audit->record(
                event: 'account.passkey.renamed',
                actor: $user,
                subject: $user,
                metadata: ['passkey_public_id' => (string) $passkey->public_id],
            );
            $this->securityNotifications->publish(
                userId: $userId,
                event: 'account.passkey.renamed',
                title: (string) __('accounts.security.passkey_renamed.title'),
                body: (string) __('accounts.security.passkey_renamed.body'),
                idempotencyKey: 'account.passkey.renamed:'.$userId.':'.$passkey->public_id.':'.sha1($name),
            );
        });
    }
}
