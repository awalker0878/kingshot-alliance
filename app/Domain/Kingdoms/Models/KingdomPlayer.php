<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $kingdom_id
 * @property string|null $game_player_id
 * @property string $current_name
 * @property-read Kingdom $kingdom
 */
final class KingdomPlayer extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kingdom_id',
        'game_player_id',
        'current_name',
    ];

    /** @return BelongsTo<Kingdom, $this> */
    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class);
    }

    /** @return HasMany<AllianceRosterEntry, $this> */
    public function rosterEntries(): HasMany
    {
        return $this->hasMany(AllianceRosterEntry::class);
    }
}
