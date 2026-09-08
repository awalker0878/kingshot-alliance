<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Models;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceIdentitySource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $kingdom_alliance_id
 * @property string $name
 * @property string|null $tag
 * @property string|null $game_alliance_id
 * @property \Illuminate\Support\Carbon $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_to
 * @property KingdomAllianceIdentitySource $source_type
 * @property string|null $source_reference
 * @property \Illuminate\Support\Carbon|null $observed_at
 * @property int|null $confidence_basis_points
 * @property string|null $reason
 */
final class KingdomAllianceIdentityHistory extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'kingdom_alliance_identity_history';

    protected $fillable = [
        'kingdom_alliance_id',
        'name',
        'tag',
        'game_alliance_id',
        'valid_from',
        'valid_to',
        'source_type',
        'source_reference',
        'observed_at',
        'confidence_basis_points',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'immutable_datetime',
            'valid_to' => 'immutable_datetime',
            'source_type' => KingdomAllianceIdentitySource::class,
            'observed_at' => 'immutable_datetime',
            'confidence_basis_points' => 'integer',
        ];
    }

    /** @return BelongsTo<KingdomAlliance, $this> */
    public function kingdomAlliance(): BelongsTo
    {
        return $this->belongsTo(KingdomAlliance::class);
    }
}
