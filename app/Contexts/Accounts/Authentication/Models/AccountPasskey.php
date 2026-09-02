<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Models;

use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Passkey;

/** @property string $public_id */
final class AccountPasskey extends Passkey
{
    protected static function booted(): void
    {
        self::creating(static function (self $passkey): void {
            if (blank($passkey->public_id)) {
                $passkey->public_id = (string) Str::uuid();
            }
        });

        self::deleting(static function (self $passkey): void {
            if (! app(AccountSignInMethodPolicy::class)->canRemovePasskey((int) $passkey->user_id, (int) $passkey->id)) {
                throw ValidationException::withMessages([
                    'passkey' => 'Add another sign-in method before removing this passkey.',
                ]);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
