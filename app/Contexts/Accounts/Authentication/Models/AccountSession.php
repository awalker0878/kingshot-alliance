<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Models;

use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Privacy-conscious registry for real Laravel sessions. The configured session handler remains authoritative.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $session_id_hash
 * @property string $session_id
 * @property string|null $browser_family
 * @property string|null $platform_family
 * @property string|null $device_family
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $revoked_at
 */
final class AccountSession extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'session_id_hash',
        'session_id',
        'browser_family',
        'platform_family',
        'device_family',
        'first_seen_at',
        'last_seen_at',
        'revoked_at',
    ];

    protected $hidden = ['session_id', 'session_id_hash'];

    protected function casts(): array
    {
        return [
            'session_id' => 'encrypted',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
