<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Queries;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use Illuminate\Support\Str;

final class AccountIdentityQuery
{
    public function find(int $userId): ?AccountIdentity
    {
        $user = User::query()->find($userId);

        return $user instanceof User ? $this->snapshot($user) : null;
    }

    public function require(int $userId): AccountIdentity
    {
        return $this->snapshot(User::query()->findOrFail($userId));
    }

    public function findIdByEmail(string $email): ?int
    {
        $id = User::query()
            ->where('email', Str::lower(trim($email)))
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function exists(int $userId): bool
    {
        return User::query()->whereKey($userId)->exists();
    }

    private function snapshot(User $user): AccountIdentity
    {
        return new AccountIdentity(
            userId: (int) $user->id,
            name: (string) $user->name,
            email: (string) $user->email,
            emailVerified: $user->hasVerifiedEmail(),
            multiFactorConfirmed: $user->two_factor_confirmed_at !== null,
            anonymized: $user->anonymized_at !== null,
        );
    }
}
