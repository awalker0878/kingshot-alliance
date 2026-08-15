<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\KingdomAlliance;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventPlayerContext extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id',
        'player_id',
        'player_name_snapshot',
        'represented_alliance_id',
        'represented_kingdom_alliance_id',
        'represented_alliance_name_snapshot',
        'represented_alliance_tag_snapshot',
        'kingdom_id_at_event',
        'context_frozen_at',
    ];

    protected function casts(): array
    {
        return [
            'context_frozen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    /** @return BelongsTo<Alliance, $this> */
    public function representedAlliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class, 'represented_alliance_id');
    }

    /** @return BelongsTo<KingdomAlliance, $this> */
    public function representedKingdomAlliance(): BelongsTo
    {
        return $this->belongsTo(KingdomAlliance::class, 'represented_kingdom_alliance_id');
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function kingdomAtEvent(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'kingdom_id_at_event');
    }
}
