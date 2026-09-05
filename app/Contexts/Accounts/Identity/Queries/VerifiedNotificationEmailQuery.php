<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Queries;

use App\Contexts\Accounts\Identity\Models\User;

final readonly class VerifiedNotificationEmailQuery
{
    public function forUser(int $userId): ?string
    {
        $user = User::query()->whereKey($userId)->first(['email', 'email_verified_at']);
        if (! $user instanceof User || $user->email_verified_at === null) {
            return null;
        }

        $email = trim((string) $user->email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }
}
