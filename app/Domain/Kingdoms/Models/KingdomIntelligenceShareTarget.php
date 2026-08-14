<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareTargetState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $kingdom_intelligence_share_id
 * @property string $tracked_kingdom_alliance_id
 * @property KingdomIntelligenceShareTargetState $state
 * @property int $shared_by_player_id
 * @property int|null $removed_by_player_id
 * @property Carbon $shared_at
 * @property Carbon|null $removed_at
 * @property-read KingdomIntelligenceShare $share
 * @property-read TrackedKingdomAlliance $tracking
 */
final class KingdomIntelligenceShareTarget extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kingdom_intelligence_share_id',
        'tracked_kingdom_alliance_id',
        'state',
        'shared_by_player_id',
        'removed_by_player_id',
        'shared_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => KingdomIntelligenceShareTargetState::class,
            'shared_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<KingdomIntelligenceShare, $this> */
    public function share(): BelongsTo
    {
        return $this->belongsTo(KingdomIntelligenceShare::class, 'kingdom_intelligence_share_id');
    }

    /** @return BelongsTo<TrackedKingdomAlliance, $this> */
    public function tracking(): BelongsTo
    {
        return $this->belongsTo(TrackedKingdomAlliance::class, 'tracked_kingdom_alliance_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'shared_by_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'removed_by_player_id');
    }
}
