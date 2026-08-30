<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $user_id
 * @property int $granted_by_user_id
 * @property CarbonImmutable $granted_at
 * @property CarbonImmutable|null $revoked_at
 */
final class GiftCodeCuratorGrant extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'granted_by_user_id',
        'granted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
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
