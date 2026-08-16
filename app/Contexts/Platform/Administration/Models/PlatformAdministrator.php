<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Models;

use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $granted_at @property Carbon|null $revoked_at */
final class PlatformAdministrator extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['user_id', 'granted_by_user_id', 'granted_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function activeFor(User $user): bool
    {
        return self::query()->where('user_id', $user->id)->whereNull('revoked_at')->exists();
    }
}
