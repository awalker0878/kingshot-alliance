<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Identity\Actions\RecordAccountIdentityUse;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ConnectGoogleAccount
{
    public function __construct(
        private RecordAccountIdentityUse $recordIdentityUse,
        private AuditRecorder $audit,
        private SecurityNotificationService $securityNotifications,
    ) {}

    public function handle(int $userId, string $verifiedSubject, string $verifiedEmail): int
    {
        $subject = trim($verifiedSubject);
        $email = Str::lower(trim($verifiedEmail));
        if ($subject === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['google' => 'A verified Google identity is required.']);
        }

        try {
            return DB::transaction(function () use ($userId, $subject, $email): int {
                $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                $user->ensureActive();
                $identity = AccountIdentity::query()->where('user_id', $userId)
                    ->where('provider', 'google')->lockForUpdate()->first();

                if ($identity !== null) {
                    if (! hash_equals((string) $identity->provider_subject, $subject)) {
                        throw ValidationException::withMessages([
                            'google' => 'A different Google account is already connected. Disconnect it before connecting another one.',
                        ]);
                    }
                    $this->recordIdentityUse->handle((int) $identity->id, $email, true);

                    return (int) $identity->id;
                }

                $identity = AccountIdentity::query()->create([
                    'user_id' => $userId,
                    'provider' => 'google',
                    'provider_subject' => $subject,
                    'provider_email' => $email,
                    'provider_email_verified_at' => now(),
                    'linked_at' => now(),
                    'last_used_at' => now(),
                ]);
                $this->audit->record(event: 'auth.google.identity_created', actor: $user, subject: $user, metadata: ['provider' => 'google']);
                $this->audit->record(event: 'account.google.connected', actor: $user, subject: $user);
                $this->securityNotifications->publish(
                    userId: $userId,
                    event: 'account.google.connected',
                    title: (string) __('accounts.security.google_connected.title'),
                    body: (string) __('accounts.security.google_connected.body'),
                    idempotencyKey: 'account.google.connected:'.$userId.':'.Str::ulid(),
                );

                return (int) $identity->id;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Only credential uniqueness is a user conflict; failures in audit
            // or Communications persistence must retain their original error.
            if (! str_contains($exception->getSql(), 'insert into "account_identities"')) {
                throw $exception;
            }
            $this->audit->record(
                event: 'account.google.connection_rejected',
                actor: User::query()->findOrFail($userId),
                metadata: ['reason' => 'credential_conflict'],
            );

            throw ValidationException::withMessages([
                'google' => 'This Google account could not be connected safely because it is already in use.',
            ]);
        }
    }
}
