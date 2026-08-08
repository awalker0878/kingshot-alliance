<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $kingdom_player_id
 * @property string|null $membership_id
 * @property string $observed_name
 * @property string|null $game_role
 * @property RosterState $state
 * @property Carbon|null $joined_at
 * @property Carbon|null $left_at
 * @property string|null $manager_notes
 * @property Carbon|null $last_observed_at
 * @property string $source
 * @property-read KingdomPlayer $player
 * @property-read AllianceMembership|null $membership
 */
final class AllianceRosterEntry extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'kingdom_player_id',
        'membership_id',
        'observed_name',
        'game_role',
        'state',
        'joined_at',
        'left_at',
        'manager_notes',
        'last_observed_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'state' => RosterState::class,
            'joined_at' => 'date',
            'left_at' => 'datetime',
            'last_observed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<KingdomPlayer, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(KingdomPlayer::class, 'kingdom_player_id');
    }

    /** @return BelongsTo<AllianceMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(AllianceMembership::class, 'membership_id');
    }

    /** @return HasMany<PlayerSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(PlayerSnapshot::class, 'roster_entry_id');
    }
}
