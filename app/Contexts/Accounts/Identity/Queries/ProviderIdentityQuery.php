<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Queries;

use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\ValueObjects\ProviderIdentity;
use Illuminate\Support\Str;

final class ProviderIdentityQuery
{
    public function findByProviderSubject(string $provider, string $providerSubject): ?ProviderIdentity
    {
        $identity = AccountIdentity::query()
            ->where('provider', Str::lower(trim($provider)))
            ->where('provider_subject', trim($providerSubject))
            ->first();

        return $identity instanceof AccountIdentity ? $this->snapshot($identity) : null;
    }

    public function findForUser(int $userId, string $provider): ?ProviderIdentity
    {
        $identity = AccountIdentity::query()
            ->where('user_id', $userId)
            ->where('provider', Str::lower(trim($provider)))
            ->first();

        return $identity instanceof AccountIdentity ? $this->snapshot($identity) : null;
    }

    private function snapshot(AccountIdentity $identity): ProviderIdentity
    {
        return new ProviderIdentity(
            identityId: (int) $identity->id,
            userId: (int) $identity->user_id,
            provider: (string) $identity->provider,
            providerSubject: (string) $identity->provider_subject,
            providerEmail: $identity->provider_email === null ? null : (string) $identity->provider_email,
            providerEmailVerified: $identity->provider_email_verified_at !== null,
        );
    }
}
