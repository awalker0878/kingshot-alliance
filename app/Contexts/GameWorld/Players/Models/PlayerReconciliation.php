<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Models;

use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $canonical_player_id
 * @property string $duplicate_player_id
 * @property string $reason
 * @property PlayerIdentitySource $source_type
 * @property string|null $source_reference
 * @property int|null $confidence_basis_points
 * @property Carbon $reconciled_at
 */
final class PlayerReconciliation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'canonical_player_id',
        'duplicate_player_id',
        'reason',
        'source_type',
        'source_reference',
        'confidence_basis_points',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => PlayerIdentitySource::class,
            'confidence_basis_points' => 'integer',
            'reconciled_at' => 'immutable_datetime',
        ];
    }
}
