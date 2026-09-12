<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Models;

use Illuminate\Support\Str;
use Laravel\Passkeys\Passkey;

/** @property string $public_id */
final class AccountPasskey extends Passkey
{
    protected $table = 'passkeys';

    protected static function booted(): void
    {
        self::creating(static function (self $passkey): void {
            if (blank($passkey->public_id)) {
                $passkey->public_id = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
