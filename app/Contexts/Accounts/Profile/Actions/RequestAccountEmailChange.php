<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Actions;

use App\Contexts\Accounts\EmailVerification\Notifications\VerifyPendingKingshotAllianceEmail;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RequestAccountEmailChange
{
    public function __construct(
        private AuditRecorder $audit,
        private SecurityNotificationService $securityNotifications,
    ) {}

    public function handle(int $userId, string $email): void
    {
        $email = Str::lower(trim($email));
        $pendingEmail = DB::transaction(function () use ($userId, $email): string {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            if (! $user->supportsPasswordAuthentication()) {
                throw ValidationException::withMessages([
                    'email' => 'Google sign-in accounts use the verified email supplied by Google.',
                ]);
            }

            if (hash_equals(Str::lower((string) $user->email), $email)) {
                throw ValidationException::withMessages(['email' => 'Choose a different email address.']);
            }

            if (User::query()
                ->where('id', '<>', $userId)
                ->where(static function ($query) use ($email): void {
                    $query->where('email', $email)->orWhere('pending_email', $email);
                })
                ->exists()) {
                throw ValidationException::withMessages(['email' => 'The email has already been taken.']);
            }

            $user->forceFill([
                'pending_email' => $email,
                'pending_email_requested_at' => now(),
            ])->save();

            $this->audit->record(
                event: 'auth.email.change_requested',
                actor: $user,
                subject: $user,
            );

            return $email;
        });

        Notification::route('mail', $pendingEmail)->notify(
            new VerifyPendingKingshotAllianceEmail($userId, sha1($pendingEmail)),
        );

        $this->securityNotifications->publish(
            userId: $userId,
            event: 'auth.email.change_requested',
            title: (string) __('accounts.security.email_change_requested.title'),
            body: (string) __('accounts.security.email_change_requested.body'),
            idempotencyKey: 'auth.email.change_requested:'.$userId.':'.sha1($pendingEmail),
        );
    }
}
