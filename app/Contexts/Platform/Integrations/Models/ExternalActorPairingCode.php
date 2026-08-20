<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Models;

use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $player_id
 * @property ExternalActorProvider $provider
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $cancelled_at
 */
final class ExternalActorPairingCode extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'player_id',
        'provider',
        'code_hash',
        'expires_at',
        'consumed_at',
        'cancelled_at',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'provider' => ExternalActorProvider::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
