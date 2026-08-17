<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Models;

use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceDiplomacyState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $tracked_kingdom_alliance_id
 * @property string $kingdom_alliance_id
 * @property KingdomAllianceDiplomacyState $current_state
 * @property Carbon $effective_at
 * @property Carbon|null $review_at
 * @property Carbon|null $expires_at
 * @property string|null $terms
 * @property string|null $rationale
 * @property string|null $last_transition_player_id
 * @property-read TrackedKingdomAlliance $tracking
 */
final class KingdomAllianceDiplomacy extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'kingdom_alliance_diplomacy_relationships';

    protected $fillable = [
        'alliance_id',
        'tracked_kingdom_alliance_id',
        'kingdom_alliance_id',
        'current_state',
        'effective_at',
        'review_at',
        'expires_at',
        'terms',
        'rationale',
        'last_transition_player_id',
    ];

    protected function casts(): array
    {
        return [
            'current_state' => KingdomAllianceDiplomacyState::class,
            'effective_at' => 'datetime',
            'review_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TrackedKingdomAlliance, $this> */
    public function tracking(): BelongsTo
    {
        return $this->belongsTo(TrackedKingdomAlliance::class, 'tracked_kingdom_alliance_id');
    }

    /** @return HasMany<KingdomAllianceDiplomacyTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(KingdomAllianceDiplomacyTransition::class, 'diplomacy_relationship_id');
    }
}
