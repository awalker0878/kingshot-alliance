<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Platform-owned administrative grant keyed to an Accounts user by scalar ID.
 *
 * @property int $user_id
 * @property int|null $granted_by_user_id
 * @property Carbon $granted_at
 * @property Carbon|null $revoked_at
 */
final class PlatformAdministrator extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'granted_by_user_id', 'granted_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'granted_by_user_id' => 'integer',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function activeForUserId(int $userId): bool
    {
        return self::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->exists();
    }
}
