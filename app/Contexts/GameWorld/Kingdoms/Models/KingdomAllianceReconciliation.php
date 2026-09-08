<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Models;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceIdentitySource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $kingdom_id
 * @property string $canonical_kingdom_alliance_id
 * @property string $duplicate_kingdom_alliance_id
 * @property string $reason
 * @property KingdomAllianceIdentitySource $source_type
 * @property string|null $source_reference
 * @property int|null $confidence_basis_points
 * @property Carbon $reconciled_at
 */
final class KingdomAllianceReconciliation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kingdom_id',
        'canonical_kingdom_alliance_id',
        'duplicate_kingdom_alliance_id',
        'reason',
        'source_type',
        'source_reference',
        'confidence_basis_points',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => KingdomAllianceIdentitySource::class,
            'confidence_basis_points' => 'integer',
            'reconciled_at' => 'immutable_datetime',
        ];
    }
}
