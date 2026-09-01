<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Actions;

use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RecordAccountIdentityUse
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        int $identityId,
        ?string $providerEmail,
        bool $providerEmailVerified,
    ): void {
        $email = $providerEmail === null ? null : Str::lower(trim($providerEmail));

        DB::transaction(function () use ($identityId, $email, $providerEmailVerified): void {
            $identity = AccountIdentity::query()->whereKey($identityId)->lockForUpdate()->firstOrFail();
            $user = User::query()->whereKey($identity->user_id)->lockForUpdate()->firstOrFail();

            if ($providerEmailVerified && $email !== null && $email !== '' && ! hash_equals(Str::lower((string) $user->email), $email)) {
                if (User::query()
                    ->where('id', '<>', $user->id)
                    ->where(static function ($query) use ($email): void {
                        $query->where('email', $email)->orWhere('pending_email', $email);
                    })
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'google' => 'The verified email supplied by Google is already used by another Kingshot Alliance account.',
                    ]);
                }

                $user->forceFill([
                    'email' => $email,
                    'email_verified_at' => now(),
                ])->save();
                $this->audit->record(
                    event: 'auth.email.provider_updated',
                    actor: $user,
                    subject: $user,
                    metadata: ['provider' => $identity->provider],
                );
            }

            $identity->forceFill([
                'provider_email' => $email,
                'provider_email_verified_at' => $providerEmailVerified ? now() : $identity->provider_email_verified_at,
                'last_used_at' => now(),
            ])->save();

            $this->audit->record(
                event: 'auth.'.$identity->provider.'.identity_used',
                actor: $user,
                subject: $user,
                metadata: ['provider' => $identity->provider],
            );
        });
    }
}
