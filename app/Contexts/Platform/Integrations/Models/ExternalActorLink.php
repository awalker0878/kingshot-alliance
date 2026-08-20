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
 * @property string $api_credential_id
 * @property ExternalActorProvider $provider
 * @property string $subject_hash
 * @property string $subject_hint
 * @property Carbon $verified_at
 * @property Carbon|null $revoked_at
 */
final class ExternalActorLink extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'player_id',
        'api_credential_id',
        'provider',
        'subject_hash',
        'subject_hint',
        'verified_at',
        'revoked_at',
    ];

    protected $hidden = ['subject_hash'];

    protected function casts(): array
    {
        return [
            'provider' => ExternalActorProvider::class,
            'verified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
