<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Durable external authentication identity owned by Accounts.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_subject
 * @property string|null $provider_email
 * @property Carbon|null $provider_email_verified_at
 * @property Carbon $linked_at
 * @property Carbon|null $last_used_at
 */
final class AccountIdentity extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_subject',
        'provider_email',
        'provider_email_verified_at',
        'linked_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_email_verified_at' => 'datetime',
            'linked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
