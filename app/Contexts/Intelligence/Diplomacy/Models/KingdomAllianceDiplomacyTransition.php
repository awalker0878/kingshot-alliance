<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Models;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceDiplomacyState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $diplomacy_relationship_id
 * @property string $tracked_kingdom_alliance_id
 * @property string $kingdom_alliance_id
 * @property KingdomAllianceDiplomacyState $from_state
 * @property KingdomAllianceDiplomacyState $to_state
 * @property Carbon $effective_at
 * @property Carbon|null $review_at
 * @property Carbon|null $expires_at
 * @property string|null $terms
 * @property string|null $rationale
 * @property string|null $actor_player_id
 * @property Carbon $created_at
 * @property-read Player|null $actor
 */
final class KingdomAllianceDiplomacyTransition extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'diplomacy_relationship_id',
        'tracked_kingdom_alliance_id',
        'kingdom_alliance_id',
        'from_state',
        'to_state',
        'effective_at',
        'review_at',
        'expires_at',
        'terms',
        'rationale',
        'actor_player_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_state' => KingdomAllianceDiplomacyState::class,
            'to_state' => KingdomAllianceDiplomacyState::class,
            'effective_at' => 'datetime',
            'review_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<KingdomAllianceDiplomacy, $this> */
    public function relationship(): BelongsTo
    {
        return $this->belongsTo(KingdomAllianceDiplomacy::class, 'diplomacy_relationship_id');
    }

    /** @return BelongsTo<TrackedKingdomAlliance, $this> */
    public function tracking(): BelongsTo
    {
        return $this->belongsTo(TrackedKingdomAlliance::class, 'tracked_kingdom_alliance_id');
    }

    /** @return BelongsTo<KingdomAlliance, $this> */
    public function kingdomAlliance(): BelongsTo
    {
        return $this->belongsTo(KingdomAlliance::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'actor_player_id');
    }
}
