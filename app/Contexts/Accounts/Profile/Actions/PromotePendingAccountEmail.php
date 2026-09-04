<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Actions;

use App\Contexts\Accounts\EmailVerification\Notifications\KingshotAllianceEmailChangedNotice;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final readonly class PromotePendingAccountEmail
{
    public function __construct(
        private AuditRecorder $audit,
        private SecurityNotificationService $securityNotifications,
    ) {}

    public function handle(int $userId, string $hash): void
    {
        [$previousEmail, $email] = DB::transaction(function () use ($userId, $hash): array {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $pendingEmail = (string) $user->pending_email;

            if ($pendingEmail === '' || ! hash_equals(sha1($pendingEmail), $hash)) {
                throw ValidationException::withMessages([
                    'email' => 'This email-change verification link is no longer valid.',
                ]);
            }

            if (User::query()->where('id', '<>', $userId)->where('email', $pendingEmail)->exists()) {
                throw ValidationException::withMessages(['email' => 'The email has already been taken.']);
            }

            $previousEmail = (string) $user->email;
            $user->forceFill([
                'email' => $pendingEmail,
                'email_verified_at' => now(),
                'pending_email' => null,
                'pending_email_requested_at' => null,
            ])->save();

            $this->audit->record(
                event: 'auth.email.changed',
                actor: $user,
                subject: $user,
            );

            return [$previousEmail, $pendingEmail];
        });

        Notification::route('mail', $previousEmail)->notify(new KingshotAllianceEmailChangedNotice($email));
        $this->securityNotifications->publish(
            userId: $userId,
            event: 'auth.email.changed',
            title: (string) __('accounts.security.email_changed.title'),
            body: (string) __('accounts.security.email_changed.body'),
            idempotencyKey: 'auth.email.changed:'.$userId.':'.sha1($email),
        );
    }
}
