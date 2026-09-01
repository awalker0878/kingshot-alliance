<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Actions;

use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class CreateAccountIdentity
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        int $userId,
        string $provider,
        string $providerSubject,
        ?string $providerEmail,
        bool $providerEmailVerified,
    ): AccountIdentity {
        $provider = Str::lower(trim($provider));
        $providerSubject = trim($providerSubject);
        $providerEmail = $providerEmail === null ? null : Str::lower(trim($providerEmail));

        if ($provider === '' || $providerSubject === '') {
            throw new InvalidArgumentException('Provider and provider subject are required.');
        }

        return DB::transaction(function () use (
            $userId,
            $provider,
            $providerSubject,
            $providerEmail,
            $providerEmailVerified,
        ): AccountIdentity {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            $identity = AccountIdentity::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_subject' => $providerSubject,
                'provider_email' => $providerEmail,
                'provider_email_verified_at' => $providerEmailVerified ? now() : null,
                'linked_at' => now(),
                'last_used_at' => now(),
            ]);

            $this->audit->record(
                event: 'auth.'.$provider.'.identity_created',
                actor: $user,
                subject: $user,
                metadata: ['provider' => $provider],
            );

            return $identity;
        });
    }
}
