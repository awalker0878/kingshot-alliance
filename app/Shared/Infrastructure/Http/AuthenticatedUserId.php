<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

final class AuthenticatedUserId
{
    public static function from(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof Authenticatable, 401);

        $identifier = $user->getAuthIdentifier();
        abort_unless(is_int($identifier) || (is_string($identifier) && ctype_digit($identifier)), 401);

        return (int) $identifier;
    }

    public static function optional(Request $request): ?int
    {
        $user = $request->user();
        if (! $user instanceof Authenticatable) {
            return null;
        }

        $identifier = $user->getAuthIdentifier();
        if (! is_int($identifier) && (! is_string($identifier) || ! ctype_digit($identifier))) {
            return null;
        }

        return (int) $identifier;
    }
}
