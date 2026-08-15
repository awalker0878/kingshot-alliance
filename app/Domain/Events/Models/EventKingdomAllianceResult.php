<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Kingdoms\Models\KingdomAlliance;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventKingdomAllianceResult extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id',
        'kingdom_alliance_id',
        'alliance_name_snapshot',
        'alliance_tag_snapshot',
        'outcome',
        'score',
        'rank',
        'notes',
        'recorded_by_player_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'rank' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    /** @return BelongsTo<KingdomAlliance, $this> */
    public function kingdomAlliance(): BelongsTo
    {
        return $this->belongsTo(KingdomAlliance::class, 'kingdom_alliance_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function recordedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'recorded_by_player_id');
    }

    /** @return HasMany<EventKingdomAllianceResultMetric, $this> */
    public function metrics(): HasMany
    {
        return $this->hasMany(EventKingdomAllianceResultMetric::class, 'event_kingdom_alliance_result_id');
    }
}
