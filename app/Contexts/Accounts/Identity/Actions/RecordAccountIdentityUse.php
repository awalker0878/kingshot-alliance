<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Actions;

use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Str;

final readonly class RecordAccountIdentityUse
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        AccountIdentity $identity,
        ?string $providerEmail,
        bool $providerEmailVerified,
    ): AccountIdentity {
        $user = $identity->user()->firstOrFail();
        $email = $providerEmail === null ? null : Str::lower(trim($providerEmail));

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

        return $identity->refresh();
    }
}
